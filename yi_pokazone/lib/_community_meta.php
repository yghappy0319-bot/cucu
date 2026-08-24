<?php
/**
 * 커뮤니티 카테고리 / 네이버 카페 연동 공통 정의
 */

if (!defined('COMMUNITY_SOURCE_POKAZONE')) {
    define('COMMUNITY_SOURCE_POKAZONE', 'pokazone');
}
if (!defined('COMMUNITY_SOURCE_NAVER_TCG')) {
    define('COMMUNITY_SOURCE_NAVER_TCG', 'naver_tcg');
}
if (!defined('COMMUNITY_SOURCE_NAVER_MVC')) {
    define('COMMUNITY_SOURCE_NAVER_MVC', 'naver_mvc');
}
if (!defined('COMMUNITY_CAT_TCG_CAFE')) {
    define('COMMUNITY_CAT_TCG_CAFE', 'tcg_cafe');
}
if (!defined('COMMUNITY_CAT_MVC_CAFE')) {
    define('COMMUNITY_CAT_MVC_CAFE', 'mvc_cafe');
}

/** 목록 탭용 (전체 포함 가능) */
function community_categories(bool $include_all = true): array
{
    $cats = [
        'free'                  => '자유',
        'brag'                  => '자랑',
        'question'              => '질문',
        'info'                  => '정보',
        'tip'                   => '꿀팁',
        COMMUNITY_CAT_TCG_CAFE  => '포켓몬TCG카페',
        COMMUNITY_CAT_MVC_CAFE  => '포켓몬MVC',
    ];
    if ($include_all) {
        return ['' => '전체'] + $cats;
    }

    return $cats;
}

/** 사용자가 글쓰기 가능한 카테고리 (외부 연동 탭 제외) */
function community_writable_categories(): array
{
    $cats = community_categories(false);
    unset($cats[COMMUNITY_CAT_TCG_CAFE], $cats[COMMUNITY_CAT_MVC_CAFE]);

    return $cats;
}

/** 외부 연동(네이버 카페) 카테고리 여부 */
function community_is_external_category(string $cat): bool
{
    return in_array($cat, [COMMUNITY_CAT_TCG_CAFE, COMMUNITY_CAT_MVC_CAFE], true);
}

function community_category_label(string $key): string
{
    $all = community_categories(false);

    return $all[$key] ?? '자유';
}

function community_has_external_columns(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    if (!db_table_exists('tb_community')) {
        $ok = false;

        return false;
    }
    $ok = (bool) db_assoc(db_query("SHOW COLUMNS FROM tb_community LIKE 'co_source'"));

    return $ok;
}

/**
 * 네이버 카페 연동용 시스템 회원 mb_idx
 */
function community_naver_cafe_bot_mb_idx(string $bot_id, string $nick, string $email): int
{
    static $cache = [];
    if (isset($cache[$bot_id])) {
        return $cache[$bot_id];
    }

    $row = db_assoc(db_query(
        "SELECT mb_idx FROM tb_member WHERE mb_id = '" . db_escape($bot_id) . "' LIMIT 1"
    ));
    if ($row) {
        $cache[$bot_id] = (int) $row['mb_idx'];

        return $cache[$bot_id];
    }

    $by_nick = db_assoc(db_query(
        "SELECT mb_idx FROM tb_member WHERE mb_nick = '" . db_escape($nick) . "' LIMIT 1"
    ));
    if ($by_nick) {
        $cache[$bot_id] = (int) $by_nick['mb_idx'];

        return $cache[$bot_id];
    }

    $pw        = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    $esc_id    = db_escape($bot_id);
    $esc_pw    = db_escape($pw);
    $esc_name  = db_escape('네이버카페연동');
    $esc_nick  = db_escape($nick);
    $esc_email = db_escape($email);

    $ok = db_query("
        INSERT INTO tb_member
            (mb_id, mb_pw, mb_name, mb_nick, mb_email, mb_level, mb_status,
             mb_agree_terms, mb_agree_privacy, mb_created_at, mb_updated_at)
        VALUES
            ('{$esc_id}', '{$esc_pw}', '{$esc_name}', '{$esc_nick}', '{$esc_email}',
             1, 1, 1, 1, NOW(), NOW())
    ");
    if (!$ok) {
        $cache[$bot_id] = 0;

        return 0;
    }
    $cache[$bot_id] = (int) db_insert_id();

    return $cache[$bot_id];
}

function community_naver_tcg_bot_mb_idx(): int
{
    return community_naver_cafe_bot_mb_idx(
        'naver_tcg_bot',
        '포켓몬TCG카페',
        'naver_tcg_bot@pokazone.local'
    );
}

function community_naver_mvc_bot_mb_idx(): int
{
    return community_naver_cafe_bot_mb_idx(
        'naver_mvc_bot',
        '포켓몬MVC',
        'naver_mvc_bot@pokazone.local'
    );
}

/** 목록/상세에 표시할 작성자명 */
function community_display_author(array $row): string
{
    $ext = trim((string) ($row['co_external_nick'] ?? ''));
    if ($ext !== '') {
        return $ext;
    }

    return (string) ($row['mb_nick'] ?? '(탈퇴)');
}

/** 네이버 카페 연동 글 여부 (TCG / MVC) */
function community_is_external_cafe_post(array $row): bool
{
    $source = (string) ($row['co_source'] ?? COMMUNITY_SOURCE_POKAZONE);
    if (in_array($source, [COMMUNITY_SOURCE_NAVER_TCG, COMMUNITY_SOURCE_NAVER_MVC], true)) {
        return true;
    }

    return community_is_external_category((string) ($row['co_category'] ?? ''));
}

/** @deprecated use community_is_external_cafe_post */
function community_is_naver_tcg_post(array $row): bool
{
    return community_is_external_cafe_post($row);
}

function community_canonical_external_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }
    $parts = parse_url($url);
    if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
        return $url;
    }
    $path = $parts['path'] ?? '';

    return $parts['scheme'] . '://' . $parts['host'] . $path;
}
