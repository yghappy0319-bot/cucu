<?php
/**
 * 네이버 카페 크롤 JSON → tb_community INSERT (중복 URL 제외)
 *
 * CLI:
 *   php naver_cafe_board_import.php [json] [source] [category] [cafe_label] [bot]
 *
 * bot: tcg | mvc
 * 예)
 *   php naver_cafe_board_import.php naver_cafe_posts.json naver_tcg tcg_cafe 포켓몬TCG커뮤니티 tcg
 *   php naver_cafe_board_import.php naver_cafe_mvc_posts.json naver_mvc mvc_cafe 포켓몬카드MVC mvc
 */
require_once __DIR__ . '/../lib/_function.php';
require_once __DIR__ . '/../lib/_community_meta.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

$json_path  = $argv[1] ?? (__DIR__ . '/naver_cafe_posts.json');
$co_source  = $argv[2] ?? COMMUNITY_SOURCE_NAVER_TCG;
$co_category = $argv[3] ?? COMMUNITY_CAT_TCG_CAFE;
$cafe_label = $argv[4] ?? '포켓몬TCG커뮤니티';
$bot_key    = strtolower(trim((string) ($argv[5] ?? 'tcg')));

if (!is_file($json_path)) {
    fwrite(STDERR, "JSON not found: {$json_path}\n");
    exit(2);
}

if (!community_has_external_columns()) {
    fwrite(STDERR, "DB columns missing. Run sql/migrate_tb_community_naver_tcg.sql first.\n");
    exit(3);
}

$allowed_sources = [COMMUNITY_SOURCE_NAVER_TCG, COMMUNITY_SOURCE_NAVER_MVC];
$allowed_cats    = [COMMUNITY_CAT_TCG_CAFE, COMMUNITY_CAT_MVC_CAFE];
if (!in_array($co_source, $allowed_sources, true) || !in_array($co_category, $allowed_cats, true)) {
    fwrite(STDERR, "Invalid source/category\n");
    exit(6);
}

$raw = file_get_contents($json_path);
$posts = json_decode((string) $raw, true);
if (!is_array($posts)) {
    fwrite(STDERR, "Invalid JSON\n");
    exit(4);
}

if ($bot_key === 'mvc') {
    $mb_idx = community_naver_mvc_bot_mb_idx();
    $bot_name = 'naver_mvc_bot';
} else {
    $mb_idx = community_naver_tcg_bot_mb_idx();
    $bot_name = 'naver_tcg_bot';
}
if ($mb_idx < 1) {
    fwrite(STDERR, "Failed to resolve system member ({$bot_name})\n");
    exit(5);
}

function naver_cafe_parse_created_at(string $raw): string
{
    $s = trim(str_replace(' ', '', $raw));
    if ($s === '') {
        return '';
    }
    if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $s, $m)) {
        $ss = isset($m[3]) ? (int) $m[3] : 0;
        return date('Y-m-d') . sprintf(' %02d:%02d:%02d', (int) $m[1], (int) $m[2], $ss);
    }
    if (preg_match('/^(\d{4})[.\-\/](\d{1,2})[.\-\/](\d{1,2})\.?$/', $s, $m)) {
        return sprintf('%04d-%02d-%02d 00:00:00', (int) $m[1], (int) $m[2], (int) $m[3]);
    }
    if (preg_match('/^(\d{1,2})[.\-\/](\d{1,2})\.?$/', $s, $m)) {
        return sprintf('%04d-%02d-%02d 00:00:00', (int) date('Y'), (int) $m[1], (int) $m[2]);
    }
    return '';
}

function naver_cafe_parse_views($raw): int
{
    if (is_int($raw) || is_float($raw)) {
        return max(0, (int) $raw);
    }
    $s = trim(str_replace([',', ' '], '', (string) $raw));
    if ($s === '' || $s === '-' || $s === '조회') {
        return 0;
    }
    if (preg_match('/^(\d+(?:\.\d+)?)만$/u', $s, $m)) {
        return (int) round(((float) $m[1]) * 10000);
    }
    if (preg_match('/^(\d+(?:\.\d+)?)천$/u', $s, $m)) {
        return (int) round(((float) $m[1]) * 1000);
    }
    if (preg_match('/^\d+$/', $s)) {
        return (int) $s;
    }
    return 0;
}

$inserted = 0;
$skipped  = 0;
$safe_cafe = htmlspecialchars($cafe_label, ENT_QUOTES, 'UTF-8');

foreach ($posts as $post) {
    if (!is_array($post)) {
        continue;
    }
    $title = trim((string) ($post['title'] ?? ''));
    $nick  = trim((string) ($post['nickname'] ?? ''));
    $url   = community_canonical_external_url((string) ($post['url'] ?? ''));

    if ($title === '' || $url === '') {
        $skipped++;
        continue;
    }
    if (mb_strlen($title) > 150) {
        $title = mb_substr($title, 0, 150);
    }

    $created_at = trim((string) ($post['created_at'] ?? ''));
    if ($created_at === '' || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $created_at)) {
        $created_at = naver_cafe_parse_created_at((string) ($post['date'] ?? ''));
    }
    if ($created_at === '') {
        $created_at = date('Y-m-d H:i:s');
    }

    $views = isset($post['views']) ? naver_cafe_parse_views($post['views']) : 0;
    if ($views < 1 && isset($post['read_count_raw'])) {
        $views = naver_cafe_parse_views($post['read_count_raw']);
    }

    $esc_url = db_escape($url);
    $exists  = db_assoc(db_query(
        "SELECT co_idx FROM tb_community WHERE co_external_url = '{$esc_url}' LIMIT 1"
    ));
    if ($exists) {
        $skipped++;
        continue;
    }

    $esc_title   = db_escape($title);
    $esc_nick    = db_escape($nick);
    $esc_created = db_escape($created_at);
    $views_i     = (int) $views;

    $safe_nick = htmlspecialchars($nick !== '' ? $nick : '알 수 없음', ENT_QUOTES, 'UTF-8');
    $safe_href = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

    $content = '<p>네이버 카페 <strong>' . $safe_cafe . '</strong> 원문입니다.</p>'
        . '<p>작성자: ' . $safe_nick . '</p>'
        . '<p><a href="' . $safe_href . '" target="_blank" rel="noopener noreferrer">원문 보러가기</a></p>';

    $esc_content = db_escape($content);
    $esc_cat     = db_escape($co_category);
    $esc_source  = db_escape($co_source);

    $ok = db_query("
        INSERT INTO tb_community
            (mb_idx, co_category, co_source, co_external_url, co_external_nick,
             co_title, co_content, co_views, co_status, co_ip, co_created_at, co_updated_at)
        VALUES
            ({$mb_idx}, '{$esc_cat}', '{$esc_source}', '{$esc_url}', '{$esc_nick}',
             '{$esc_title}', '{$esc_content}', {$views_i}, 1, '127.0.0.1',
             '{$esc_created}', '{$esc_created}')
    ");

    if ($ok) {
        $inserted++;
    } else {
        $skipped++;
        fwrite(STDERR, 'INSERT fail: ' . db_last_error() . "\n");
    }
}

echo "import done. source={$co_source} inserted={$inserted} skipped={$skipped} total=" . count($posts) . "\n";
exit(0);
