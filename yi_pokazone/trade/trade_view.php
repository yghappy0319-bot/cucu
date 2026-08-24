<?php
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_community_html.php';
require_once __DIR__ . '/../lib/_member_public.php';
require_once __DIR__ . '/../lib/card_price_feed.php';
require_once __DIR__ . '/lib/_trade_comment.php';

$idx = (int)($_GET['idx'] ?? 0);
if ($idx < 1) {
    alert_goto('잘못된 접근입니다.', '/trade/trade.php');
}

$types = [
    'sell'     => '판매',
    'buy'      => '구매',
    'exchange' => '교환',
];
$methods = [
    'direct'   => '직거래',
    'delivery' => '택배',
    'both'     => '직거래 / 택배',
];
$conds = [
    'S' => '민트 (미개봉/완벽)',
    'A' => '상급 (거의새것)',
    'B' => '중급 (사용감있음)',
    'C' => '하급 (보관용)',
];
$languages = [
    'ko'    => '한글판',
    'ja'    => '일본판',
    'en'    => '영문판',
    'other' => '기타',
];
$deal_status_labels = [
    1 => ['label' => '판매중',   'class' => 'ds-active'],
    2 => ['label' => '거래중',   'class' => 'ds-reserved'],
    3 => ['label' => '거래완료', 'class' => 'ds-done'],
];

if (!isset($_SESSION['tr_view']) || !is_array($_SESSION['tr_view'])) {
    $_SESSION['tr_view'] = [];
}
$__ad_view = login_admin() !== null;

if (!in_array($idx, $_SESSION['tr_view'], true)) {
    db_query("UPDATE tb_trade SET tr_views = tr_views + 1 WHERE tr_idx = {$idx} AND tr_status = 1");
    $_SESSION['tr_view'][] = $idx;
}

$rs = db_query("
    SELECT t.*, m.mb_nick, m.mb_level, m.mb_created_at AS author_join
    FROM tb_trade t
    LEFT JOIN tb_member m ON m.mb_idx = t.mb_idx
    WHERE t.tr_idx = {$idx}
    LIMIT 1
");
$row = db_assoc($rs);

if (!$row) {
    alert_goto('존재하지 않는 거래글입니다.', '/trade/trade.php');
}

// 이미지 로드
$rs_img = db_query("SELECT * FROM tb_trade_image WHERE tr_idx = {$idx} ORDER BY ti_order ASC, ti_idx ASC");
$images = [];
while ($im = db_assoc($rs_img)) $images[] = $im;

if ($__ad_view) {
    trade_hold_columns_ready();
}

$me       = login_member();
$is_held = trade_is_held($row);
$is_deleted = trade_is_deleted_status($row);
$is_deleted_public = $is_deleted && !$__ad_view;
$is_held_public = $is_held && !$__ad_view;
$hide_content_public = $is_deleted_public || $is_held_public;
$is_owner = $me && (int)$me['mb_idx'] === (int)$row['mb_idx'];
$is_admin = $me && (int)$me['mb_level'] >= 9;
$can_edit = ($is_owner && !$is_held) || $is_admin;

$has_wish = db_table_exists('tb_trade_like');
$wished = false;
if ($has_wish && $me) {
    $wished = (bool) db_assoc(db_query("
        SELECT 1 AS o FROM tb_trade_like
        WHERE tr_idx = {$idx} AND mb_idx = " . (int) $me['mb_idx'] . " LIMIT 1
    "));
}
$can_wish = $has_wish && (int) $row['tr_status'] === TRADE_STATUS_OK && (int) $row['tr_deal_status'] !== 3;
$return_path = '/trade/trade_view.php?idx=' . $idx;

$has_comments = trade_comment_table_ready();
$can_comment  = $has_comments && (int) $row['tr_status'] === TRADE_STATUS_OK && !$hide_content_public;
$comment_rows = [];
$comment_children = [];
$comment_count = 0;
if ($has_comments) {
    $comment_rows = trade_comment_list($idx);
    $comment_count = count($comment_rows);
    foreach ($comment_rows as $c) {
        if ($c['tc_parent_idx'] !== null && (int) $c['tc_parent_idx'] > 0) {
            $pid = (int) $c['tc_parent_idx'];
            if (!isset($comment_children[$pid])) {
                $comment_children[$pid] = [];
            }
            $comment_children[$pid][] = $c;
        }
    }
}
$comment_point_cost = trade_comment_point_cost();

$trade_chat_ok = db_table_exists('tb_trade_room') && db_table_exists('tb_trade_room_msg');
$payment_ready = trade_chat_payment_status()['ready'];
$can_buy_now   = !$hide_content_public
    && !$can_edit
    && ($row['tr_type'] ?? '') === 'sell'
    && (int) $row['tr_price'] > 0
    && (int) $row['tr_deal_status'] === 1
    && $trade_chat_ok
    && $payment_ready;
$checkout_return = '/trade/trade_checkout.php?tr_idx=' . $idx;

$type_label  = $types[$row['tr_type']] ?? '판매';
$cond_label  = $conds[$row['tr_condition']] ?? '상급';
$method_lbl  = $methods[$row['tr_method']] ?? '직거래 / 택배';
$ds          = $deal_status_labels[(int)$row['tr_deal_status']] ?? $deal_status_labels[1];
$ds['label'] = trade_deal_status_label((int)$row['tr_deal_status'], $row['tr_type'] ?? 'sell');
$is_box      = $row['tr_item_type'] === 'box';
$lang_label  = $languages[$row['tr_language'] ?? ''] ?? '';
$is_graded   = !$is_box && ($row['tr_card_kind'] ?? '') === 'graded';
$shipping_fee_view = 0;
if (function_exists('trade_checkout_shipping_fee')) {
    $shipping_fee_view = trade_checkout_shipping_fee($row);
} elseif (array_key_exists('tr_shipping_fee', $row)) {
    $m = (string) ($row['tr_method'] ?? '');
    if ($m === 'delivery' || $m === 'both') {
        $shipping_fee_view = max(0, (int) $row['tr_shipping_fee']);
    }
}
$grading_label = '';
if ($is_graded && !empty($row['tr_grading_company']) && !empty($row['tr_grading_score'])) {
    $grading_label = trim((string) $row['tr_grading_company'] . ' ' . (string) $row['tr_grading_score']);
}

$card_price_market = ($is_box && card_price_tables_ready() && !$is_deleted_public)
    ? card_price_lookup_box_market((string) $row['tr_card_name'])
    : null;

$hold_reason_text = $is_held ? trade_hold_reason_text($row) : '';
$deleted_excerpt = '';
if ($is_deleted_public) {
    $plain = strip_tags((string) $row['tr_content']);
    $plain = str_replace(["\r\n", "\r"], "\n", $plain);
    $lines = [];
    foreach (explode("\n", $plain) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $lines[] = $line;
        if (count($lines) >= 2) {
            break;
        }
    }
    $total_lines = 0;
    foreach (explode("\n", $plain) as $line) {
        if (trim($line) !== '') {
            $total_lines++;
        }
    }
    if (!empty($lines)) {
        $deleted_excerpt = implode("\n", $lines);
        if ($total_lines > count($lines)) {
            $deleted_excerpt .= '…';
        }
    }
}

$page  = 'trade';
$title = $row['tr_title'];
$meta_description = seo_clean_desc(
    $is_held_public
        ? $row['tr_title'] . ' · 게시중지' . ($hold_reason_text !== '' ? ' · ' . $hold_reason_text : '')
        : ($is_deleted_public
        ? $row['tr_title'] . ' · ' . $row['tr_card_name'] .
          ($deleted_excerpt !== '' ? ' · ' . str_replace("\n", ' ', $deleted_excerpt) : '') .
          ' · 삭제된 상품입니다'
        : ($is_box ? '미개봉 상자' : '카드') . ' · ' . $row['tr_card_name'] .
          ($row['tr_grade'] ? ' · ' . $row['tr_grade'] : '') .
          ($row['tr_price'] > 0 ? ' · ₩' . number_format((int)$row['tr_price']) : ' · 가격제안') .
          ' · ' . $row['tr_content']),
    160
);
$meta_type = 'product';
$meta_keywords = implode(', ', array_filter([
    $row['tr_card_name'],
    $row['tr_set_name'] ?? '',
    $row['tr_card_number'] ?? '',
    $lang_label,
    $grading_label,
    $is_box ? '미개봉 상자' : '포켓몬카드',
    $row['tr_grade'] ?? '',
    $type_label,
    $row['tr_region'] ?? '',
    '포켓몬카드 거래',
]));
$first_image = !empty($images) ? $images[0]['ti_path'] : '';
$meta_image  = $first_image;
$meta_published_at = date('c', strtotime($row['tr_created_at']));
$meta_modified_at  = date('c', strtotime($row['tr_updated_at']));
$meta_author       = $row['mb_nick'] ?? '';
$share_url         = seo_abs_url('/trade/trade_view.php?idx=' . (int)$row['tr_idx']);
$meta_breadcrumb   = [
    ['name' => '홈', 'url' => '/'],
    ['name' => '거래게시판', 'url' => '/trade/trade.php'],
    ['name' => $type_label, 'url' => '/trade/trade.php?type=' . urlencode($row['tr_type'])],
    ['name' => $row['tr_title'], 'url' => '/trade/trade_view.php?idx=' . (int)$row['tr_idx']],
];

// Product 구조화 데이터
$__avail_map = [
    1 => 'https://schema.org/InStock',
    2 => 'https://schema.org/PreOrder',
    3 => 'https://schema.org/SoldOut',
];
$meta_jsonld = [
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => $row['tr_card_name'],
    'description' => $is_held_public
        ? seo_clean_desc('게시중지된 거래글입니다. ' . $hold_reason_text, 300)
        : ($is_deleted_public
        ? seo_clean_desc(
            ($deleted_excerpt !== '' ? $deleted_excerpt . ' ' : '') . '삭제된 상품입니다',
            300
        )
        : seo_clean_desc($row['tr_content'], 300)),
    'sku'         => 'TR-' . (int)$row['tr_idx'],
    'category'    => $is_box ? '미개봉 상자' : '포켓몬 카드',
    'image'       => array_values(array_map(function($im){ return seo_abs_url($im['ti_path']); }, $images)),
    'brand'       => [
        '@type' => 'Brand',
        'name'  => 'Pokémon TCG',
    ],
];
if (!empty($row['tr_grade'])) {
    $meta_jsonld['additionalProperty'] = [
        ['@type' => 'PropertyValue', 'name' => '레어도', 'value' => $row['tr_grade']],
    ];
}
if (!empty($row['tr_set_name'])) {
    if (!isset($meta_jsonld['additionalProperty'])) {
        $meta_jsonld['additionalProperty'] = [];
    }
    $meta_jsonld['additionalProperty'][] = [
        '@type' => 'PropertyValue',
        'name'  => '세트',
        'value' => $row['tr_set_name'],
    ];
}
if (!empty($row['tr_card_number'])) {
    if (!isset($meta_jsonld['additionalProperty'])) {
        $meta_jsonld['additionalProperty'] = [];
    }
    $meta_jsonld['additionalProperty'][] = [
        '@type' => 'PropertyValue',
        'name'  => '카드번호',
        'value' => $row['tr_card_number'],
    ];
}
if ($grading_label !== '') {
    if (!isset($meta_jsonld['additionalProperty'])) {
        $meta_jsonld['additionalProperty'] = [];
    }
    $meta_jsonld['additionalProperty'][] = [
        '@type' => 'PropertyValue',
        'name'  => '슬랩등급',
        'value' => $grading_label,
    ];
}
if ((int)$row['tr_price'] > 0 || $hide_content_public) {
    $meta_jsonld['offers'] = [
        '@type'         => 'Offer',
        'url'           => seo_abs_url('/trade/trade_view.php?idx=' . (int)$row['tr_idx']),
        'priceCurrency' => 'KRW',
        'price'         => (int)$row['tr_price'],
        'availability'  => $hide_content_public
            ? 'https://schema.org/Discontinued'
            : ($__avail_map[(int)$row['tr_deal_status']] ?? 'https://schema.org/InStock'),
        'itemCondition' => $row['tr_condition'] === 'S'
            ? 'https://schema.org/NewCondition'
            : 'https://schema.org/UsedCondition',
        'seller' => [
            '@type' => 'Person',
            'name'  => $meta_author,
        ],
    ];
}
if (($__ad_view && (int) $row['tr_status'] !== TRADE_STATUS_OK) || $is_held || $is_deleted) {
    $meta_noindex = true;
}

$similar_limit = 6;
$similar_rows = [];
$similar_search_q = (string) $row['tr_card_name'];
if (!$is_deleted_public) {
    $card_esc = db_escape((string) $row['tr_card_name']);
    $item_type_esc = db_escape((string) $row['tr_item_type']);
    $similar_terms = trade_similar_match_terms((string) $row['tr_card_name'], (string) $row['tr_title']);
    if (!empty($similar_terms)) {
        $similar_search_q = $similar_terms[0];
    }
    $primary_term_esc = db_escape($similar_search_q);
    $similar_where = trade_similar_match_where_sql(
        (string) $row['tr_card_name'],
        (string) $row['tr_title'],
        (string) $row['tr_item_type'],
        $idx
    );
    $rs_similar = db_query("
        SELECT t.*, m.mb_nick,
               (SELECT ti_path FROM tb_trade_image
                 WHERE tr_idx = t.tr_idx
                 ORDER BY ti_order ASC, ti_idx ASC LIMIT 1) AS thumb_path,
               (t.tr_card_name = '{$card_esc}') AS _name_exact,
               (t.tr_item_type = '{$item_type_esc}') AS _type_match,
               (
                   CASE WHEN t.tr_card_name = '{$card_esc}' THEN 100 ELSE 0 END
                   + CASE WHEN '{$card_esc}' LIKE CONCAT('%', t.tr_card_name, '%') THEN 40 ELSE 0 END
                   + CASE WHEN '{$card_esc}' LIKE CONCAT('%', t.tr_title, '%') THEN 30 ELSE 0 END
                   + CASE
                       WHEN t.tr_card_name LIKE '%{$primary_term_esc}%' OR t.tr_title LIKE '%{$primary_term_esc}%'
                       THEN 20
                       ELSE 0
                     END
               ) AS _sim_score
        FROM tb_trade t
        LEFT JOIN tb_member m ON m.mb_idx = t.mb_idx
        WHERE {$similar_where}
        ORDER BY _sim_score DESC, _name_exact DESC, _type_match DESC,
                 CASE WHEN t.tr_deal_status = 3 THEN 1 ELSE 0 END ASC,
                 t.tr_idx DESC
        LIMIT {$similar_limit}
    ");
    while ($sr = db_assoc($rs_similar)) {
        $similar_rows[] = $sr;
    }
}
$similar_wishes = ($has_wish && $me && !empty($similar_rows))
    ? trade_user_wished_map(array_column($similar_rows, 'tr_idx'), (int) $me['mb_idx'])
    : [];
$similar_more_qs = http_build_query(array_filter([
    'q'    => $similar_search_q,
    'item' => $is_box ? 'box' : ($row['tr_card_kind'] ?? ''),
    'type' => $row['tr_type'] !== 'sell' ? $row['tr_type'] : '',
], static function ($v) {
    return $v !== '' && $v !== null;
}));

include __DIR__ . '/../include/header.php';
?>

<section class="community trade-detail">
    <div class="container">
        <?php if ($__ad_view && $is_deleted): ?>
            <p style="margin:0 0 1rem;padding:0.75rem 1rem;border-radius:8px;border:1px solid #c7d2fe;background:#eef2ff;color:#3730a3;font-size:14px;">
                관리자 미리보기 · 사용자에게는 숨김(삭제) 처리된 거래글입니다.
            </p>
        <?php endif; ?>
        <?php if ($__ad_view && (int) $row['tr_status'] !== TRADE_STATUS_DELETED): ?>
            <?php include __DIR__ . '/include/trade_hold_admin.php'; ?>
        <?php endif; ?>
        <article class="post trade-post" data-tr-idx="<?php echo (int) $row['tr_idx']; ?>">
            <header class="post-head">
                <div class="post-cat">
                    <span class="cat-badge type-<?php echo htmlspecialchars($row['tr_type']); ?>">
                        <?php echo htmlspecialchars($type_label); ?>
                    </span>
                    <span class="item-type-chip item-<?php echo $is_box ? 'box' : 'card'; ?>">
                        <?php echo $is_box ? '📦 상자' : '카드'; ?>
                    </span>
                    <?php
                    $__hck = !$is_box && !empty($row['tr_card_kind']) ? $row['tr_card_kind'] : '';
                    if ($__hck === 'single' || $__hck === 'graded'): ?>
                        <span class="item-kind-chip item-<?php echo htmlspecialchars($__hck); ?>">
                            <?php echo $__hck === 'graded' ? '등급' : '싱글'; ?>
                        </span>
                    <?php endif; ?>
                    <span class="deal-status <?php echo $ds['class']; ?>"><?php echo $ds['label']; ?></span>
                    <?php if ($is_held): ?>
                    <span class="deal-status ds-hold">게시중지</span>
                    <?php endif; ?>
                </div>
                <h1 class="post-title"><?php echo htmlspecialchars($row['tr_title']); ?></h1>

                <div class="post-meta">
                    <div class="post-author">
                        <?php
                        $author_mb = (int) ($row['mb_idx'] ?? 0);
                        $author_nick = (string) ($row['mb_nick'] ?? '');
                        $author_url = member_profile_url($author_mb);
                        $author_label = $author_nick !== '' ? $author_nick : '(탈퇴회원)';
                        ?>
                        <?php if ($author_url !== ''): ?>
                        <a href="<?php echo htmlspecialchars($author_url, ENT_QUOTES, 'UTF-8'); ?>" class="post-author-link">
                            <span class="user-avatar">
                                <?php echo mb_substr(htmlspecialchars($author_label), 0, 1); ?>
                            </span>
                            <strong><?php echo htmlspecialchars($author_label); ?></strong>
                        </a>
                        <a href="<?php echo htmlspecialchars($author_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline btn-sm trade-shop-btn">상점 보기</a>
                        <?php else: ?>
                        <span class="user-avatar">
                            <?php echo mb_substr(htmlspecialchars($author_label), 0, 1); ?>
                        </span>
                        <strong><?php echo htmlspecialchars($author_label); ?></strong>
                        <?php endif; ?>
                    </div>
                    <div class="post-stats">
                        <span>작성 <?php echo date('Y-m-d H:i', strtotime($row['tr_created_at'])); ?></span>
                        <?php if ($row['tr_updated_at'] !== $row['tr_created_at']): ?>
                            <span>· 수정 <?php echo date('Y-m-d H:i', strtotime($row['tr_updated_at'])); ?></span>
                        <?php endif; ?>
                        <span>· 조회 <?php echo number_format((int)$row['tr_views']); ?></span>
                        <?php if ($has_wish): ?>
                            <span data-trade-wish-stat<?php echo (int) $row['tr_likes'] < 1 ? ' hidden' : ''; ?>>· 찜 <?php echo number_format((int) $row['tr_likes']); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <?php if ($is_deleted_public): ?>
                <p class="trade-deleted-banner" role="status">삭제된 상품입니다</p>
            <?php endif; ?>

            <div class="trade-detail-layout<?php echo $is_deleted_public ? ' trade-detail-layout--deleted' : ''; ?>">
                <div class="trade-detail-media">
                    <?php if (!$is_deleted_public && !empty($images)): ?>
                        <?php $alt_base = ($is_box ? '미개봉 상자 ' : '포켓몬 카드 ') . $row['tr_card_name']; ?>
                        <figure class="gallery trade-view-gallery" data-gallery>
                            <div class="gallery-main" data-gallery-main>
                                <div class="gallery-main__viewport">
                                    <img src="<?php echo htmlspecialchars(public_url($images[0]['ti_path'])); ?>"
                                         alt="<?php echo htmlspecialchars($alt_base . ' 대표 이미지'); ?>"
                                         data-main-image
                                         fetchpriority="high"
                                         decoding="async">
                                    <div class="gallery-magnifier" hidden data-gallery-lens aria-hidden="true">
                                        <img src="" alt="" class="gallery-magnifier__img" draggable="false" decoding="async">
                                    </div>
                                </div>
                            </div>
                            <?php if (count($images) > 1): ?>
                                <div class="gallery-thumbs" role="list">
                                    <?php foreach ($images as $i => $im): ?>
                                        <button type="button"
                                                class="gallery-thumb <?php echo $i === 0 ? 'is-active' : ''; ?>"
                                                data-src="<?php echo htmlspecialchars(public_url($im['ti_path'])); ?>"
                                                aria-label="<?php echo htmlspecialchars($alt_base . ' 이미지 ' . ($i + 1)); ?>">
                                            <img src="<?php echo htmlspecialchars(public_url($im['ti_path'])); ?>"
                                                 alt="<?php echo htmlspecialchars($alt_base . ' 이미지 ' . ($i + 1)); ?>"
                                                 loading="lazy" decoding="async">
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </figure>
                    <?php elseif (!$is_deleted_public): ?>
                        <div class="trade-detail-placeholder" aria-hidden="true">
                            <div class="trade-placeholder-tcg">
                                <span class="trade-placeholder-tcg-inner"></span>
                            </div>
                            <p>등록된 이미지 없음</p>
                        </div>
                    <?php endif; ?>
                </div>

                <aside class="trade-detail-panel" aria-label="상품 정보">
                    <?php if (!empty($author_url)): ?>
                        <div class="trade-seller-shop-card">
                            <div class="trade-seller-shop-card__head">
                                <span class="user-avatar" aria-hidden="true"><?php echo mb_substr(htmlspecialchars($author_label), 0, 1); ?></span>
                                <div class="trade-seller-shop-card__meta">
                                    <span class="trade-seller-shop-card__label">판매자 상점</span>
                                    <strong class="trade-seller-shop-card__nick"><?php echo htmlspecialchars($author_label); ?></strong>
                                </div>
                            </div>
                            <a href="<?php echo htmlspecialchars($author_url, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline btn-block trade-shop-btn-panel">상점 보기</a>
                        </div>
                    <?php endif; ?>
                    <?php if ($is_deleted_public): ?>
                        <div class="trade-spec trade-spec--deleted trade-spec--panel">
                            <dl>
                                <dt><?php echo $is_box ? '상자명' : '카드명'; ?></dt>
                                <dd><?php echo htmlspecialchars($row['tr_card_name']); ?></dd>
                            </dl>
                            <?php if (!$is_box && !empty($row['tr_grade'])): ?>
                            <dl>
                                <dt>레어도</dt>
                                <dd><span class="spec-chip"><?php echo htmlspecialchars($row['tr_grade']); ?></span></dd>
                            </dl>
                            <?php endif; ?>
                            <dl>
                                <dt>거래방식</dt>
                                <dd><?php echo htmlspecialchars($method_lbl); ?></dd>
                            </dl>
                        </div>
                    <?php else: ?>
                        <div class="trade-detail-pricebox">
                            <span class="trade-detail-pricebox__label">희망가</span>
                            <?php if ((int)$row['tr_price'] > 0): ?>
                                <p class="trade-detail-pricebox__price">
                                    <small>₩</small><?php echo number_format((int)$row['tr_price']); ?>
                                </p>
                            <?php else: ?>
                                <p class="trade-detail-pricebox__price trade-detail-pricebox__price--offer">가격제안 받음</p>
                            <?php endif; ?>
                            <?php if ($shipping_fee_view > 0): ?>
                                <p class="trade-detail-pricebox__ship">택배비 ₩<?php echo number_format($shipping_fee_view); ?> · 바로구매 시 합산</p>
                            <?php elseif ((($row['tr_method'] ?? '') === 'delivery' || ($row['tr_method'] ?? '') === 'both')): ?>
                                <p class="trade-detail-pricebox__ship">택배비 판매자 부담</p>
                            <?php endif; ?>
                            <p class="trade-detail-pricebox__name"><?php echo htmlspecialchars($row['tr_card_name']); ?></p>
                        </div>

                        <div class="trade-spec trade-spec--panel">
                            <dl>
                                <dt>상품 종류</dt>
                                <dd>
                                    <span class="item-type-chip item-<?php echo $is_box ? 'box' : 'card'; ?>">
                                        <?php echo $is_box ? '📦 미개봉 상자/박스' : '카드 (TCG)'; ?>
                                    </span>
                                    <?php
                                    $__sck = !$is_box && !empty($row['tr_card_kind']) ? $row['tr_card_kind'] : '';
                                    if ($__sck === 'single' || $__sck === 'graded'): ?>
                                        <span class="item-kind-chip item-<?php echo htmlspecialchars($__sck); ?>">
                                            <?php echo $__sck === 'graded' ? '등급(슬랩)' : '싱글'; ?>
                                        </span>
                                    <?php endif; ?>
                                </dd>
                            </dl>
                            <dl class="trade-spec-card-name">
                                <dt><?php echo $is_box ? '상자명' : '카드명'; ?></dt>
                                <dd><?php echo htmlspecialchars($row['tr_card_name']); ?></dd>
                            </dl>
                            <?php if ($is_box): ?>
                                <dl>
                                    <dt>수량</dt>
                                    <dd><?php echo (int)$row['tr_box_qty']; ?>개</dd>
                                </dl>
                            <?php else: ?>
                                <?php if (!empty($row['tr_set_name'])): ?>
                                <dl>
                                    <dt>세트</dt>
                                    <dd><?php echo htmlspecialchars((string) $row['tr_set_name']); ?></dd>
                                </dl>
                                <?php endif; ?>
                                <?php if (!empty($row['tr_card_number'])): ?>
                                <dl>
                                    <dt>카드번호</dt>
                                    <dd><?php echo htmlspecialchars((string) $row['tr_card_number']); ?></dd>
                                </dl>
                                <?php endif; ?>
                                <?php if ($lang_label !== ''): ?>
                                <dl>
                                    <dt>언어</dt>
                                    <dd><?php echo htmlspecialchars($lang_label); ?></dd>
                                </dl>
                                <?php endif; ?>
                                <?php if (!empty($row['tr_grade'])): ?>
                                <dl>
                                    <dt>레어도</dt>
                                    <dd><span class="spec-chip"><?php echo htmlspecialchars($row['tr_grade']); ?></span></dd>
                                </dl>
                                <?php endif; ?>
                                <?php if ($is_graded && $grading_label !== ''): ?>
                                <dl>
                                    <dt>슬랩 등급</dt>
                                    <dd><span class="spec-chip"><?php echo htmlspecialchars($grading_label); ?></span></dd>
                                </dl>
                                <?php else: ?>
                                <dl>
                                    <dt>카드 상태</dt>
                                    <dd><?php echo htmlspecialchars($cond_label); ?></dd>
                                </dl>
                                <?php endif; ?>
                            <?php endif; ?>
                            <dl>
                                <dt>거래방식</dt>
                                <dd><?php echo htmlspecialchars($method_lbl); ?></dd>
                            </dl>
                            <?php if (($row['tr_method'] ?? '') === 'delivery' || ($row['tr_method'] ?? '') === 'both'): ?>
                            <dl>
                                <dt>택배비</dt>
                                <dd><?php echo $shipping_fee_view > 0
                                    ? '₩' . number_format($shipping_fee_view) . ' (구매자 부담)'
                                    : '판매자 부담'; ?></dd>
                            </dl>
                            <?php endif; ?>
                            <?php if (!empty($row['tr_region'])): ?>
                            <dl>
                                <dt>지역</dt>
                                <dd><?php echo htmlspecialchars($row['tr_region']); ?></dd>
                            </dl>
                            <?php endif; ?>
                        </div>

                        <?php if ($card_price_market !== null): ?>
                            <div class="trade-card-price-bridge trade-card-price-bridge--panel" role="complementary" aria-label="등록된 외부 시세">
                                <p class="trade-card-price-bridge__title">박스 시세와 연결됨</p>
                                <p class="trade-card-price-bridge__text">
                                    외부 판매처 <?php echo number_format((int) $card_price_market['offer_cnt']); ?>곳과 비교할 수 있습니다.
                                    <?php if ($card_price_market['min_price_won'] !== null): ?>
                                        참고 최저 <strong>₩<?php echo number_format((int) $card_price_market['min_price_won']); ?></strong>
                                    <?php endif; ?>
                                </p>
                                <a class="btn btn-outline btn-sm"
                                   href="/page/card_price.php#<?php echo htmlspecialchars('cp-' . (int) $card_price_market['cp_idx'], ENT_QUOTES, 'UTF-8'); ?>">
                                    외부 몰 시세 보기
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if ($can_wish): ?>
                            <div class="co-like-bar trade-wish-bar trade-wish-bar--panel">
                                <form action="/trade/proc/trade_like_proc.php" method="post" class="co-like-form js-trade-wish-form">
                                    <input type="hidden" name="tr_idx" value="<?php echo (int) $row['tr_idx']; ?>">
                                    <?php if ($me): ?>
                                        <button type="submit" class="btn <?php echo $wished ? 'btn-primary' : 'btn-outline'; ?> btn-sm co-like-submit" aria-pressed="<?php echo $wished ? 'true' : 'false'; ?>">
                                            <span class="co-like-icon" aria-hidden="true"><?php echo $wished ? '♥' : '♡'; ?></span>
                                            찜 <span class="co-like-count"><?php echo number_format((int) $row['tr_likes']); ?></span>
                                        </button>
                                    <?php else: ?>
                                        <a class="btn btn-outline btn-sm" href="/login.php?return=<?php echo rawurlencode($return_path); ?>">♡ 로그인 후 찜</a>
                                        <span class="co-like-readonly"><?php echo number_format((int) $row['tr_likes']); ?>명이 찜했어요</span>
                                    <?php endif; ?>
                                </form>
                            </div>
                        <?php elseif ($has_wish): ?>
                            <div class="co-like-bar co-like-bar--muted trade-wish-bar trade-wish-bar--panel">
                                <span class="co-like-readonly">찜 <?php echo number_format((int) $row['tr_likes']); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($can_edit): ?>
                            <form class="deal-status-form deal-status-form--panel" action="/trade/proc/trade_status_proc.php" method="post">
                                <input type="hidden" name="idx" value="<?php echo (int)$row['tr_idx']; ?>">
                                <span class="deal-status-label">거래 상태 변경:</span>
                                <?php foreach ($deal_status_labels as $k => $info): ?>
                                    <button type="submit" name="status" value="<?php echo $k; ?>"
                                            class="btn btn-sm <?php echo (int)$row['tr_deal_status'] === $k ? 'btn-primary' : 'btn-outline'; ?>">
                                        <?php echo htmlspecialchars(trade_deal_status_label((int) $k, $row['tr_type'] ?? 'sell')); ?>
                                    </button>
                                <?php endforeach; ?>
                            </form>
                        <?php endif; ?>

                        <div class="trade-detail-actions<?php echo $can_edit ? ' trade-detail-actions--owner' : ''; ?>">
                            <?php if ($is_held && !$can_edit): ?>
                                <p class="trade-hold-actions-hint">게시중지된 거래글은 문의·구매를 할 수 없습니다.</p>
                            <?php elseif ($can_edit): ?>
                                <?php if ($trade_chat_ok && (int) $row['tr_status'] === 1): ?>
                                    <a href="/trade/trade_messages.php?tr_idx=<?php echo (int)$row['tr_idx']; ?>" class="btn btn-primary btn-block">문의함</a>
                                <?php endif; ?>
                                <a href="/trade/trade_write.php?idx=<?php echo (int)$row['tr_idx']; ?>" class="btn btn-outline btn-block">수정</a>
                                <form action="/trade/proc/trade_delete_proc.php" method="post" class="inline-form trade-detail-actions__form"
                                      onsubmit="return confirm('이 거래글을 삭제하시겠습니까? 목록에서는 보이지 않습니다.');">
                                    <input type="hidden" name="idx" value="<?php echo (int)$row['tr_idx']; ?>">
                                    <button type="submit" class="btn btn-danger btn-block">삭제</button>
                                </form>
                            <?php elseif ($me): ?>
                                <?php if ($trade_chat_ok): ?>
                                    <a href="/trade/trade_messages.php?tr_idx=<?php echo (int)$row['tr_idx']; ?>" class="btn btn-primary btn-block">거래 문의</a>
                                <?php else: ?>
                                    <span class="btn btn-primary btn-block" style="opacity:0.6;cursor:not-allowed;" title="채팅 DB 미설치">거래 문의</span>
                                <?php endif; ?>
                                <?php if ($can_buy_now): ?>
                                    <a href="<?php echo htmlspecialchars($checkout_return, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-outline btn-block trade-buy-now-btn">바로구매</a>
                                <?php endif; ?>
                            <?php else: ?>
                                <a href="/login.php?return=<?php echo urlencode('/trade/trade_messages.php?tr_idx=' . (int)$row['tr_idx']); ?>" class="btn btn-primary btn-block">로그인 후 문의</a>
                                <?php if ($can_buy_now): ?>
                                    <a href="/login.php?return=<?php echo urlencode($checkout_return); ?>" class="btn btn-outline btn-block trade-buy-now-btn">로그인 후 바로구매</a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>

            <div class="trade-detail-body">
                <h2 class="trade-detail-body__title">상세 설명</h2>
                <div class="post-body post-body--article<?php echo ($is_deleted_public || $is_held_public) ? ' post-body--deleted-summary' : ''; ?>">
                    <?php if ($is_held_public): ?>
                        <div class="trade-hold-notice trade-hold-notice--body" role="status">
                            <p class="trade-hold-notice__title">게시중지</p>
                            <p class="trade-hold-notice__reason"><?php echo htmlspecialchars($hold_reason_text, ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="trade-hold-notice__help">운영정책에 따라 이 거래글의 본문 노출이 중지되었습니다.</p>
                        </div>
                    <?php elseif ($is_deleted_public): ?>
                        <?php if ($deleted_excerpt !== ''): ?>
                            <p class="trade-deleted-excerpt"><?php echo nl2br(htmlspecialchars($deleted_excerpt, ENT_QUOTES, 'UTF-8')); ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php echo community_render_post_body((string)$row['tr_content']); ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="post-actions trade-detail-foot">
                <div class="post-actions-left">
                    <a href="/trade/trade.php" class="btn btn-outline btn-sm">목록</a>
                    <?php if (!$is_deleted_public): ?>
                    <button type="button" class="btn btn-outline btn-sm"
                            data-share-url="<?php echo htmlspecialchars($share_url, ENT_QUOTES, 'UTF-8'); ?>"
                            data-share-label="공유하기"
                            aria-label="글 링크 복사">공유하기</button>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php if ($trade_chat_ok && (int) $row['tr_status'] === 1 && !$__ad_view): ?>
            <section class="trade-chat-hint" aria-label="거래 문의 안내">
                <p class="trade-chat-guest-hint" style="margin:0;">
                    <?php if ($me): ?>
                        <?php if ($can_edit): ?>
                            받은 문의는 <a href="/trade/trade_messages.php?tr_idx=<?php echo (int)$row['tr_idx']; ?>">거래 메시지함</a>에서 확인할 수 있습니다.
                        <?php else: ?>
                            판매자와의 대화는 <a href="/trade/trade_messages.php?tr_idx=<?php echo (int)$row['tr_idx']; ?>">거래 메시지함</a>에서 이어가실 수 있습니다.
                        <?php endif; ?>
                    <?php else: ?>
                        로그인 후 <a href="<?php echo htmlspecialchars('/trade/trade_messages.php?tr_idx=' . (int)$row['tr_idx'], ENT_QUOTES, 'UTF-8'); ?>">거래 메시지함</a>에서 판매자와 대화할 수 있습니다.
                    <?php endif; ?>
                </p>
            </section>
        <?php elseif (!$trade_chat_ok && (int) $row['tr_status'] === 1): ?>
            <section class="trade-chat-wrap trade-chat-wrap--disabled" id="trade-chat">
                <h2 class="trade-chat-title">거래 문의</h2>
                <p class="trade-chat-guest-hint">채팅 기능 사용을 위해 DB에 <code>sql/tb_trade_chat.sql</code> 을 적용해 주세요.</p>
            </section>
        <?php endif; ?>

        <?php if ($has_comments && !$is_deleted_public): ?>
            <div class="co-comments trade-comments" id="comments">
                <h2 class="co-comments-title">댓글 <span class="co-comments-count"><?php echo number_format($comment_count); ?></span></h2>

                <ul class="co-comment-list">
                    <?php
                    foreach ($comment_rows as $c):
                        if ($c['tc_parent_idx'] !== null && (int) $c['tc_parent_idx'] > 0) {
                            continue;
                        }
                        $cid = (int) $c['tc_idx'];
                        $c_owner = $me && (int) $me['mb_idx'] === (int) $c['mb_idx'];
                        $c_mod   = $me && (int) $me['mb_level'] >= 9;
                        ?>
                        <li class="co-comment" id="tc-<?php echo $cid; ?>">
                            <div class="co-comment-inner">
                                <div class="co-comment-meta">
                                    <span class="co-comment-author"><?php echo member_nick_link_html((int) ($c['mb_idx'] ?? 0), $c['mb_nick'] ?? '(회원)', 'member-nick-link'); ?></span>
                                    <time datetime="<?php echo htmlspecialchars($c['tc_created_at'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo date('Y-m-d H:i', strtotime($c['tc_created_at'])); ?></time>
                                </div>
                                <div class="co-comment-body"><?php echo nl2br(htmlspecialchars($c['tc_content'], ENT_QUOTES, 'UTF-8')); ?></div>
                                <div class="co-comment-actions">
                                    <?php if ($can_comment && $is_owner): ?>
                                        <button type="button" class="btn btn-ghost btn-sm js-reply-toggle" data-target="tr-reply-<?php echo $cid; ?>">답글</button>
                                    <?php endif; ?>
                                    <?php if ($c_owner || $c_mod || $__ad_view): ?>
                                        <form action="/trade/proc/trade_comment_delete_proc.php" method="post" class="inline-form" onsubmit="return confirm('이 댓글을 삭제할까요? 대댓글도 함께 삭제될 수 있습니다.');">
                                            <input type="hidden" name="tc_idx" value="<?php echo $cid; ?>">
                                            <input type="hidden" name="return" value="<?php echo htmlspecialchars($return_path, ENT_QUOTES, 'UTF-8'); ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm">삭제</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <?php if ($can_comment && $is_owner): ?>
                                    <div class="co-comment-reply-wrap trade-comment-reply-wrap" id="tr-reply-<?php echo $cid; ?>" hidden>
                                        <form class="co-comment-form co-comment-form--reply trade-comment-reply-form" action="/trade/proc/trade_comment_proc.php" method="post">
                                            <input type="hidden" name="tr_idx" value="<?php echo (int) $row['tr_idx']; ?>">
                                            <input type="hidden" name="return" value="<?php echo htmlspecialchars($return_path, ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="tc_parent_idx" value="<?php echo $cid; ?>">
                                            <label class="trade-comment-reply-label" for="tr-reply-body-<?php echo $cid; ?>">판매자 답글</label>
                                            <textarea id="tr-reply-body-<?php echo $cid; ?>" name="tc_content" maxlength="2000" rows="3" required placeholder="<?php echo htmlspecialchars($c['mb_nick'] ?? '', ENT_QUOTES, 'UTF-8'); ?>님에게 답글…"></textarea>
                                            <div class="trade-comment-reply-actions">
                                                <button type="submit" class="btn btn-primary btn-sm">답글 등록</button>
                                                <button type="button" class="btn btn-outline btn-sm js-reply-toggle" data-target="tr-reply-<?php echo $cid; ?>">취소</button>
                                            </div>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($comment_children[$cid])): ?>
                                <ul class="co-comment-replies">
                                    <?php foreach ($comment_children[$cid] as $ch):
                                        $chid = (int) $ch['tc_idx'];
                                        $ch_owner = $me && (int) $me['mb_idx'] === (int) $ch['mb_idx'];
                                        $ch_mod   = $me && (int) $me['mb_level'] >= 9;
                                        ?>
                                        <li class="co-comment co-comment--reply" id="tc-<?php echo $chid; ?>">
                                            <div class="co-comment-inner">
                                                <div class="co-comment-meta">
                                                    <span class="co-comment-author"><?php echo member_nick_link_html((int) ($ch['mb_idx'] ?? 0), $ch['mb_nick'] ?? '(회원)', 'member-nick-link'); ?></span>
                                                    <time datetime="<?php echo htmlspecialchars($ch['tc_created_at'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo date('Y-m-d H:i', strtotime($ch['tc_created_at'])); ?></time>
                                                </div>
                                                <div class="co-comment-body"><?php echo nl2br(htmlspecialchars($ch['tc_content'], ENT_QUOTES, 'UTF-8')); ?></div>
                                                <div class="co-comment-actions">
                                                    <?php if ($ch_owner || $ch_mod || $__ad_view): ?>
                                                        <form action="/trade/proc/trade_comment_delete_proc.php" method="post" class="inline-form" onsubmit="return confirm('답글을 삭제할까요?');">
                                                            <input type="hidden" name="tc_idx" value="<?php echo $chid; ?>">
                                                            <input type="hidden" name="return" value="<?php echo htmlspecialchars($return_path, ENT_QUOTES, 'UTF-8'); ?>">
                                                            <button type="submit" class="btn btn-ghost btn-sm">삭제</button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if (empty($comment_rows)): ?>
                    <p class="co-comments-empty">첫 댓글을 남겨 보세요.</p>
                <?php endif; ?>

                <?php if ($can_comment && $me): ?>
                    <form class="co-comment-form post-form" action="/trade/proc/trade_comment_proc.php" method="post"
                          onsubmit="return confirm('댓글 작성 시 <?php echo number_format($comment_point_cost); ?>P가 차감됩니다. 등록할까요?');">
                        <input type="hidden" name="tr_idx" value="<?php echo (int) $row['tr_idx']; ?>">
                        <input type="hidden" name="return" value="<?php echo htmlspecialchars($return_path, ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="tc_parent_idx" value="0">
                        <div class="field">
                            <label for="tc_content_root">댓글 작성</label>
                            <textarea id="tc_content_root" name="tc_content" maxlength="2000" rows="3" required placeholder="댓글을 입력하세요."></textarea>
                            <p class="help">댓글 등록 시 <?php echo number_format($comment_point_cost); ?>P가 차감됩니다.</p>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm">등록</button>
                    </form>
                <?php elseif ($can_comment && !$me): ?>
                    <p class="co-comments-login-hint"><a href="/login.php?return=<?php echo rawurlencode($return_path . '#comments'); ?>">로그인</a> 후 댓글을 작성할 수 있습니다. (작성 시 <?php echo number_format($comment_point_cost); ?>P 차감)</p>
                <?php endif; ?>
            </div>
        <?php elseif (!$has_comments && !$is_deleted_public): ?>
            <div class="co-comments trade-comments trade-comments--disabled" id="comments">
                <h2 class="co-comments-title">댓글</h2>
                <p class="co-comments-login-hint">댓글 기능을 사용하려면 DB에 <code>sql/migrate_tb_trade_comment.sql</code> 을 적용해 주세요.</p>
            </div>
        <?php endif; ?>

        <?php if (!empty($similar_rows)): ?>
            <section class="trade-similar" aria-labelledby="trade-similar-title">
                <div class="section-head trade-similar-head">
                    <h2 id="trade-similar-title">비슷한 상품</h2>
                    <div class="trade-similar-head__actions">
                        <div class="trade-similar-nav" data-trade-similar-nav hidden>
                            <button type="button" class="trade-similar-prev" aria-label="이전 상품">
                                <span aria-hidden="true">‹</span>
                            </button>
                            <button type="button" class="trade-similar-next" aria-label="다음 상품">
                                <span aria-hidden="true">›</span>
                            </button>
                        </div>
                        <a href="/trade/trade.php<?php echo $similar_more_qs !== '' ? '?' . htmlspecialchars($similar_more_qs, ENT_QUOTES, 'UTF-8') : ''; ?>" class="link-more">더보기 →</a>
                    </div>
                </div>
                <div class="trade-similar-slider">
                    <div class="swiper trade-similar-swiper" data-swiper-suppress="1" aria-label="비슷한 상품 목록">
                        <div class="swiper-wrapper">
                    <?php foreach ($similar_rows as $sim):
                        $sim_ds = $deal_status_labels[(int) $sim['tr_deal_status']] ?? $deal_status_labels[1];
                        $sim_ds['label'] = trade_deal_status_label((int) $sim['tr_deal_status'], $sim['tr_type'] ?? 'sell');
                        $sim_type_label = $types[$sim['tr_type']] ?? '판매';
                        $sim_ts = strtotime($sim['tr_created_at']);
                        $sim_is_box = $sim['tr_item_type'] === 'box';
                        $sim_img = $sim['thumb_path'] ?? '';
                        $sim_ck = !$sim_is_box && !empty($sim['tr_card_kind']) ? $sim['tr_card_kind'] : '';
                        $sim_ck_lbl = $sim_ck === 'graded' ? '등급' : ($sim_ck === 'single' ? '싱글' : '');
                    ?>
                        <div class="swiper-slide">
                        <div class="trade-item <?php echo htmlspecialchars(trade_list_item_class($sim), ENT_QUOTES, 'UTF-8'); ?>" data-tr-idx="<?php echo (int) $sim['tr_idx']; ?>">
                            <?php if ($has_wish && $me && (int) $sim['tr_status'] === 1 && (int) $sim['tr_deal_status'] !== 3): ?>
                                <?php $sim_wished = !empty($similar_wishes[(int) $sim['tr_idx']]); ?>
                                <form action="/trade/proc/trade_like_proc.php" method="post" class="trade-wish-form">
                                    <input type="hidden" name="tr_idx" value="<?php echo (int) $sim['tr_idx']; ?>">
                                    <button type="submit"
                                            class="trade-wish-btn <?php echo $sim_wished ? 'is-wished' : ''; ?>"
                                            aria-label="<?php echo $sim_wished ? '찜 해제' : '찜하기'; ?>"
                                            aria-pressed="<?php echo $sim_wished ? 'true' : 'false'; ?>">
                                        <span aria-hidden="true"><?php echo $sim_wished ? '♥' : '♡'; ?></span>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="/trade/trade_view.php?idx=<?php echo (int) $sim['tr_idx']; ?>" class="trade-item-link">
                                <div class="trade-thumb <?php echo $sim_img ? 'has-image' : ''; ?>">
                                    <div class="trade-thumb-tags">
                                        <span class="trade-type type-<?php echo htmlspecialchars($sim['tr_type']); ?>">
                                            <?php echo htmlspecialchars($sim_type_label); ?>
                                        </span>
                                        <span class="item-type-chip item-<?php echo $sim_is_box ? 'box' : 'card'; ?>">
                                            <?php echo $sim_is_box ? '📦 상자' : '카드'; ?>
                                        </span>
                                        <?php if ($sim_ck_lbl !== ''): ?>
                                            <span class="item-kind-chip item-<?php echo htmlspecialchars($sim_ck); ?>"><?php echo htmlspecialchars($sim_ck_lbl); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="deal-status <?php echo $sim_ds['class']; ?>"><?php echo $sim_ds['label']; ?></span>

                                    <?php if ($sim_img): ?>
                                        <img class="trade-image"
                                             src="<?php echo htmlspecialchars(public_url($sim_img)); ?>"
                                             alt="<?php echo htmlspecialchars(($sim_is_box ? '미개봉 상자 ' : '포켓몬 카드 ') . $sim['tr_card_name']); ?>"
                                             loading="lazy" decoding="async">
                                        <?php if ((int) $sim['tr_image_count'] > 1): ?>
                                            <span class="trade-image-count">+<?php echo (int) $sim['tr_image_count'] - 1; ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if ($sim_is_box): ?>
                                            <span class="trade-emoji" aria-hidden="true">📦</span>
                                        <?php else: ?>
                                            <span class="trade-placeholder-tcg" aria-hidden="true"><span class="trade-placeholder-tcg-inner"></span></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="trade-body">
                                    <p class="trade-title"><?php echo htmlspecialchars($sim['tr_title']); ?></p>
                                    <p class="trade-card"><?php echo htmlspecialchars($sim['tr_card_name']); ?>
                                        <?php if ($sim_is_box && (int) $sim['tr_box_qty'] > 1): ?>
                                            <span class="trade-qty">×<?php echo (int) $sim['tr_box_qty']; ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($sim['tr_grade'])): ?>
                                            <span class="trade-grade"><?php echo htmlspecialchars($sim['tr_grade']); ?></span>
                                        <?php endif; ?>
                                    </p>
                                    <div class="trade-price">
                                        <?php if ((int) $sim['tr_price'] > 0): ?>
                                            <small>₩</small><?php echo number_format((int) $sim['tr_price']); ?>
                                        <?php else: ?>
                                            <span class="trade-price-free">가격제안</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </a>
                                    <div class="trade-foot trade-foot--meta">
                                        <?php echo member_nick_link_html((int) ($sim['mb_idx'] ?? 0), $sim['mb_nick'] ?? null); ?>
                                        <span>· <?php echo date(date('Y-m-d') === date('Y-m-d', $sim_ts) ? 'H:i' : 'm/d', $sim_ts); ?></span>
                                        <span>· 조회 <?php echo number_format((int) $sim['tr_views']); ?></span>
                                        <?php
                                        $inquiry_n = (int) ($sim['tr_comments'] ?? 0);
                                        if ($inquiry_n > 0):
                                        ?>
                                            <span class="trade-inquiry-count" title="문의">· 문의 <?php echo number_format($inquiry_n); ?></span>
                                        <?php endif; ?>
                                        <?php if ($has_wish): ?>
                                            <span class="trade-wish-count" title="찜"<?php echo (int) $sim['tr_likes'] < 1 ? ' hidden' : ''; ?>>♥ <?php echo number_format((int) $sim['tr_likes']); ?></span>
                                        <?php endif; ?>
                                    </div>
                        </div>
                        </div>
                    <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </section>
        <?php endif; ?>
    </div>
</section>

<?php if (!empty($images) && !$is_deleted_public): ?>
<script>
(function(){
    const main = document.querySelector('[data-main-image]');
    const thumbs = document.querySelectorAll('.gallery-thumb');
    thumbs.forEach(btn => {
        btn.addEventListener('click', () => {
            if (!main) return;
            main.src = btn.dataset.src;
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

    main.addEventListener('load', () => {
        syncLensSrc();
    });

    mqLens.addEventListener('change', (m) => {
        if (!m.matches) hideLens();
    });

    window.addEventListener('scroll', hideLens, true);
})();
</script>
<?php endif; ?>

<?php if (isset($_GET['wrote']) && (string) $_GET['wrote'] === '1'): ?>
<script>
try {
    sessionStorage.removeItem('pz_trade_write_draft_insert');
    sessionStorage.removeItem('pz_trade_write_draft_edit_<?php echo (int) $idx; ?>');
} catch (e) {}
</script>
<?php endif; ?>

<?php include __DIR__ . '/../include/footer.php'; ?>

<?php if (!empty($similar_rows)): ?>
<script>
(function () {
    if (typeof Swiper === 'undefined') return;
    var root = document.querySelector('.trade-similar-swiper');
    if (!root || root.swiper) return;

    var nav = document.querySelector('[data-trade-similar-nav]');
    function syncNav(swiper) {
        if (!nav) return;
        var locked = swiper.isLocked || (!swiper.allowSlideNext && !swiper.allowSlidePrev);
        nav.hidden = locked;
    }

    try {
        new Swiper(root, {
            loop: false,
            watchOverflow: true,
            slidesPerView: 6,
            slidesPerGroup: 6,
            spaceBetween: 12,
            navigation: {
                prevEl: '.trade-similar-prev',
                nextEl: '.trade-similar-next',
            },
            breakpoints: {
                0: {
                    slidesPerView: 2.35,
                    slidesPerGroup: 2,
                    spaceBetween: 10,
                },
                520: {
                    slidesPerView: 3,
                    slidesPerGroup: 3,
                    spaceBetween: 10,
                },
                768: {
                    slidesPerView: 4,
                    slidesPerGroup: 4,
                    spaceBetween: 12,
                },
                1100: {
                    slidesPerView: 6,
                    slidesPerGroup: 6,
                    spaceBetween: 12,
                },
            },
            on: {
                init: syncNav,
                resize: syncNav,
                breakpoint: syncNav,
            },
        });
    } catch (e) {}
})();
</script>
<?php endif; ?>
