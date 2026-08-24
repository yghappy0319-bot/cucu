<?php
/**
 * 동적 XML 사이트맵
 *  - /sitemap.xml 로 접근 시 이 파일을 서빙하려면 .htaccess 에서 RewriteRule
 *      RewriteRule ^sitemap\.xml$ /page/sitemap.php [L]
 *    추가하거나, sitemap.php 주소를 그대로 검색엔진에 등록하세요.
 */
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_seo.php';

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$base = seo_base_url();
$now  = date('c');

$h = function($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

$add = function($loc, $lastmod = null, $priority = '0.5', $changefreq = 'weekly') use ($h) {
    echo "  <url>\n";
    echo "    <loc>" . $h($loc) . "</loc>\n";
    if ($lastmod) {
        echo "    <lastmod>" . $h($lastmod) . "</lastmod>\n";
    }
    echo "    <changefreq>" . $h($changefreq) . "</changefreq>\n";
    echo "    <priority>" . $h($priority) . "</priority>\n";
    echo "  </url>\n";
};

// ────────── 정적 페이지 ──────────
$add($base . '/',              $now, '1.0', 'daily');
$add($base . '/trade/trade.php',     $now, '0.9', 'hourly');
$add($base . '/page/community.php', $now, '0.8', 'hourly');
$add($base . '/page/notice.php',    $now, '0.7', 'daily');
$add($base . '/page/faq.php',       $now, '0.6', 'weekly');
$add($base . '/page/guide.php',     $now, '0.6', 'weekly');
$add($base . '/page/about.php',     $now, '0.5', 'monthly');
$add($base . '/page/partner.php',   $now, '0.5', 'monthly');
$add($base . '/page/terms.php',     $now, '0.3', 'yearly');
$add($base . '/page/privacy.php',   $now, '0.3', 'yearly');

$add($base . '/page/shop.php',      $now, '0.8', 'daily');
$add($base . '/page/card_price.php', $now, '0.75', 'hourly');
$add($base . '/page/market_price.php', $now, '0.75', 'hourly');

// ────────── 쇼핑몰 상세 ──────────
if (db_table_exists('tb_shop_product')) {
    $rsp = db_query('
        SELECT sp_idx, sp_created_at
        FROM tb_shop_product
        WHERE sp_status = 1
        ORDER BY sp_idx DESC
        LIMIT 5000
    ');
    if ($rsp) {
        while ($row = db_assoc($rsp)) {
            $add(
                $base . '/page/shop_view.php?idx=' . (int) $row['sp_idx'],
                date('c', strtotime($row['sp_created_at'])),
                '0.65',
                'weekly'
            );
        }
    }
}

// ────────── 공지사항 상세 ──────────
$rs = db_query("
    SELECT no_idx, no_updated_at
    FROM tb_notice
    WHERE no_status = 1
    ORDER BY no_updated_at DESC
    LIMIT 5000
");
while ($row = db_assoc($rs)) {
    $add(
        $base . '/page/notice_view.php?idx=' . (int)$row['no_idx'],
        date('c', strtotime($row['no_updated_at'])),
        '0.6',
        'weekly'
    );
}

// ────────── 거래글 상세 ──────────
$rs = db_query("
    SELECT tr_idx, tr_updated_at
    FROM tb_trade
    WHERE tr_status = 1
    ORDER BY tr_updated_at DESC
    LIMIT 5000
");
while ($row = db_assoc($rs)) {
    $add(
        $base . '/trade/trade_view.php?idx=' . (int)$row['tr_idx'],
        date('c', strtotime($row['tr_updated_at'])),
        '0.7',
        'weekly'
    );
}

// ────────── 커뮤니티 상세 ──────────
$rs = db_query("
    SELECT co_idx, co_updated_at
    FROM tb_community
    WHERE co_status = 1
    ORDER BY co_updated_at DESC
    LIMIT 5000
");
while ($row = db_assoc($rs)) {
    $add(
        $base . '/page/community_view.php?idx=' . (int)$row['co_idx'],
        date('c', strtotime($row['co_updated_at'])),
        '0.6',
        'weekly'
    );
}

echo '</urlset>';
