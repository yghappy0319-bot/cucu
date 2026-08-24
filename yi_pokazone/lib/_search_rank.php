<?php
/**
 * 실시간 인기 검색어 — 로그 기록·순위 집계
 */

const SEARCH_RANK_MIN_LEN   = 2;
const SEARCH_RANK_MAX_LEN   = 60;
const SEARCH_RANK_DEDUP_SEC = 300;
const SEARCH_RANK_DEFAULT_HOURS = 24;
const SEARCH_RANK_TREND_HOURS   = 1;

/** 인기 검색어·검색 로그에서 제외할 키워드 (성인·불법 도박 등) */
function search_keyword_block_substrings(): array
{
    return [
        '성인', '야동', '야설', '포르노', '섹스', '에로', '19금', '야한', '노출',
        '바카라', '카지노', '도박', '슬롯', '토토', '배팅', '베팅', '룰렛',
        '사설토토', '불법토토', '불법도박', '온라인카지노', '카지노사이트',
        '홀덤', '블랙잭', '파워볼', '스포츠토토', '먹튀사이트', '토토사이트',
        '카지노추천', '도박사이트', '배팅사이트', '슬롯머신', '라이브카지노',
    ];
}

/** @return string[] */
function search_keyword_block_regex(): array
{
    return [
        '/porn/i',
        '/xxx/i',
        '/baccarat/i',
        '/casino/i',
        '/gambling/i',
        '/hentai/i',
        '/nude/i',
        // 템플릿 자리표시자 / 스크립트성 문자열 차단
        '/[<>{}\[\]`\\\\|]/u',        // HTML/템플릿/스크립트에 쓰이는 특수문자
        '/\$\{/',                       // ${...} 템플릿 표현식
        '/%7[bd]/i',                    // URL 인코딩된 { } 자리표시자
        '/search_term_string/i',        // 구조화 데이터 검색 URL 자리표시자
        '/javascript\s*:/i',            // javascript: 스킴
        '/\bon[a-z]+\s*=/i',            // onerror=, onload= 등 이벤트 핸들러
        '/<\s*script/i',                // <script 태그
    ];
}

function search_keyword_is_blocked(string $keyword): bool
{
    $keyword = trim($keyword);
    if ($keyword === '') {
        return false;
    }

    $lower = mb_strtolower($keyword, 'UTF-8');
    foreach (search_keyword_block_substrings() as $term) {
        if ($term === '') {
            continue;
        }
        if (mb_stripos($lower, mb_strtolower($term, 'UTF-8'), 0, 'UTF-8') !== false) {
            return true;
        }
    }

    foreach (search_keyword_block_regex() as $pattern) {
        if (@preg_match($pattern, $keyword) === 1) {
            return true;
        }
    }

    return false;
}

function search_log_table_ready(): bool
{
    return db_table_exists('tb_search_log');
}

/** 검색어 정규화. 유효하지 않으면 null */
function search_normalize_keyword(string $q): ?string
{
    $q = trim(preg_replace('/\s+/u', ' ', $q) ?? '');
    if ($q === '') {
        return null;
    }
    $len = mb_strlen($q, 'UTF-8');
    if ($len < SEARCH_RANK_MIN_LEN || $len > SEARCH_RANK_MAX_LEN) {
        return null;
    }
    if (preg_match('/^[\s\p{P}\p{S}]+$/u', $q)) {
        return null;
    }
    if (search_keyword_is_blocked($q)) {
        return null;
    }
    return $q;
}

/** 검색 실행 시 호출 — 세션 내 동일 키워드 5분 중복 제외 */
function search_log_keyword(string $q, ?int $mb_idx = null): void
{
    if (!search_log_table_ready()) {
        return;
    }
    $kw = search_normalize_keyword($q);
    if ($kw === null) {
        return;
    }

    if (!isset($_SESSION['pz_search_log']) || !is_array($_SESSION['pz_search_log'])) {
        $_SESSION['pz_search_log'] = [];
    }
    $now = time();
    $dedup_key = mb_strtolower($kw, 'UTF-8');
    if (
        isset($_SESSION['pz_search_log'][$dedup_key])
        && ($now - (int) $_SESSION['pz_search_log'][$dedup_key]) < SEARCH_RANK_DEDUP_SEC
    ) {
        return;
    }
    $_SESSION['pz_search_log'][$dedup_key] = $now;
    foreach ($_SESSION['pz_search_log'] as $k => $t) {
        if ($now - (int) $t > SEARCH_RANK_DEDUP_SEC) {
            unset($_SESSION['pz_search_log'][$k]);
        }
    }

    $kw_esc = db_escape($kw);
    $mb_sql = ($mb_idx !== null && $mb_idx > 0) ? (string) (int) $mb_idx : 'NULL';
    db_query("INSERT INTO tb_search_log (sl_keyword, mb_idx) VALUES ('{$kw_esc}', {$mb_sql})");
}

/**
 * @return array<int, array{rank:int, keyword:string, hits:int, trend:string}>
 */
function search_rank_fetch_period(int $hours, int $offset_hours, int $limit): array
{
    $hours  = max(1, min(168, $hours));
    $limit  = max(1, min(50, $limit));
    $offset = max(0, min(168, $offset_hours));

    $sql = "
        SELECT sl_keyword AS keyword, COUNT(*) AS hits
        FROM tb_search_log
        WHERE sl_created_at >= DATE_SUB(NOW(), INTERVAL " . ($hours + $offset) . " HOUR)
          AND sl_created_at < DATE_SUB(NOW(), INTERVAL {$offset} HOUR)
        GROUP BY sl_keyword
        ORDER BY hits DESC, sl_keyword ASC
        LIMIT {$limit}
    ";
    $rs = db_query($sql);
    if (!$rs) {
        return [];
    }
    $rows = [];
    $rank = 0;
    while ($r = db_assoc($rs)) {
        $rank++;
        $rows[] = [
            'rank'    => $rank,
            'keyword' => (string) ($r['keyword'] ?? ''),
            'hits'    => (int) ($r['hits'] ?? 0),
        ];
    }
    return $rows;
}

/**
 * @param array<int, array{rank:int, keyword:string, hits:int}> $rows
 * @return array<int, array{rank:int, keyword:string, hits:int}>
 */
function search_rank_filter_rows(array $rows, int $limit): array
{
    $out = [];
    foreach ($rows as $row) {
        $kw = (string) ($row['keyword'] ?? '');
        if ($kw === '' || search_keyword_is_blocked($kw)) {
            continue;
        }
        $out[] = $row;
        if (count($out) >= $limit) {
            break;
        }
    }

    foreach ($out as $i => $row) {
        $out[$i]['rank'] = $i + 1;
    }

    return $out;
}

/**
 * @return array<int, array{rank:int, keyword:string, hits:int, trend:string, trend_delta:int}>
 */
function search_rank_top(int $limit = 10, int $hours = SEARCH_RANK_DEFAULT_HOURS): array
{
    if (!search_log_table_ready()) {
        return [];
    }

    $limit = max(1, min(20, $limit));
    $fetch_limit = min(50, max($limit * 5, $limit));
    $current = search_rank_filter_rows(search_rank_fetch_period($hours, 0, $fetch_limit), $limit);

    $trend_hours = SEARCH_RANK_TREND_HOURS;
    $trend_pool  = min(50, $fetch_limit * 2);
    $trend_now   = search_rank_fetch_period($trend_hours, 0, $trend_pool);
    $trend_prev  = search_rank_fetch_period($trend_hours, $trend_hours, $trend_pool);

    $trend_now_map = [];
    foreach ($trend_now as $row) {
        $trend_now_map[mb_strtolower($row['keyword'], 'UTF-8')] = $row['rank'];
    }
    $trend_prev_map = [];
    foreach ($trend_prev as $row) {
        $trend_prev_map[mb_strtolower($row['keyword'], 'UTF-8')] = $row['rank'];
    }

    foreach ($current as $i => $row) {
        $key = mb_strtolower($row['keyword'], 'UTF-8');
        $current[$i]['trend']       = 'same';
        $current[$i]['trend_delta'] = 0;

        if (!isset($trend_now_map[$key])) {
            continue;
        }
        if (!isset($trend_prev_map[$key])) {
            $current[$i]['trend'] = 'new';
            continue;
        }
        $now_rank  = $trend_now_map[$key];
        $prev_rank = $trend_prev_map[$key];
        if ($prev_rank > $now_rank) {
            $current[$i]['trend']       = 'up';
            $current[$i]['trend_delta'] = $prev_rank - $now_rank;
        } elseif ($prev_rank < $now_rank) {
            $current[$i]['trend']       = 'down';
            $current[$i]['trend_delta'] = $now_rank - $prev_rank;
        }
    }

    return $current;
}

function search_rank_trend_display(string $trend, int $delta = 0): string
{
    switch ($trend) {
        case 'new':
            return 'NEW';
        case 'up':
            return $delta > 0 ? '▲' . $delta : '';
        case 'down':
            return $delta > 0 ? '▼' . $delta : '';
        default:
            return '';
    }
}

function search_rank_trend_aria(string $trend, int $delta = 0): string
{
    switch ($trend) {
        case 'new':
            return '신규 진입';
        case 'up':
            return $delta > 0 ? $delta . '단계 상승' : '';
        case 'down':
            return $delta > 0 ? $delta . '단계 하락' : '';
        default:
            return '';
    }
}

/** @deprecated search_rank_trend_display() 사용 */
function search_rank_trend_label(string $trend): string
{
    return search_rank_trend_display($trend);
}

function search_rank_search_url(string $keyword): string
{
    return '/page/search.php?q=' . rawurlencode($keyword);
}
