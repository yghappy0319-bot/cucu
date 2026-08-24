<?php
/**
 * 공지사항 RSS 2.0
 *  - /rss.xml 접근 시 .htaccess 에서 rss.php 로 라우팅하거나, /page/rss.php 를 직접 등록하세요.
 */
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_seo.php';

header('Content-Type: application/rss+xml; charset=UTF-8');

$base = seo_base_url();
$feed_self = seo_abs_url(public_url('/rss.xml'));
$home      = seo_base_url() . public_url('/');

$h = static function ($s): string {
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_XML1, 'UTF-8');
};

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
echo "  <channel>\n";
echo '    <title>' . $h(SEO_SITE_NAME . ' — 공지사항') . "</title>\n";
echo '    <link>' . $h($home) . "</link>\n";
echo '    <description>' . $h('Pokazone 공지사항 — 서비스 업데이트·이벤트·점검 안내') . "</description>\n";
echo "    <language>ko-KR</language>\n";
echo '    <lastBuildDate>' . $h(date('r')) . "</lastBuildDate>\n";
echo '    <atom:link href="' . $h($feed_self) . '" rel="self" type="application/rss+xml" />' . "\n";

$rows = [];
if (db_table_exists('tb_notice')) {
    $rs = db_query("
        SELECT no_idx, no_title, no_content, no_created_at, no_updated_at
        FROM tb_notice
        WHERE no_status = 1
        ORDER BY no_is_pinned DESC, no_created_at DESC
        LIMIT 50
    ");
    while ($r = db_assoc($rs)) {
        $rows[] = $r;
    }
}

foreach ($rows as $row) {
    $link = seo_base_url() . public_url('/page/notice_view.php?idx=' . (int) $row['no_idx']);
    $ts   = $row['no_updated_at'] ?: $row['no_created_at'];
    $pub  = date('r', strtotime((string) $ts));
    $desc = seo_clean_desc($row['no_content'], 400);

    echo "    <item>\n";
    echo '      <title>' . $h($row['no_title']) . "</title>\n";
    echo '      <link>' . $h($link) . "</link>\n";
    echo '      <description>' . $h($desc) . "</description>\n";
    echo '      <pubDate>' . $h($pub) . "</pubDate>\n";
    echo '      <guid isPermaLink="true">' . $h($link) . "</guid>\n";
    echo "    </item>\n";
}

echo "  </channel>\n";
echo "</rss>\n";
