<?php
/**
 * SEO / Meta 태그 헬퍼
 *
 * 각 페이지에서 header.php 포함 전에 아래 변수들을 설정해 두면
 * header.php 가 자동으로 <head> 영역에 메타 태그를 출력합니다.
 *
 *  $title             - 페이지 타이틀 (사이트명 자동 접미)
 *  $meta_description  - 160자 이내 설명
 *  $meta_keywords     - "키워드1, 키워드2, ..."
 *  $meta_image        - OG/Twitter 카드 이미지 (절대 또는 /경로)
 *  $meta_type         - OG type (website, article, product ...)
 *  $meta_canonical    - 커스텀 canonical URL
 *  $meta_noindex      - true 일 경우 robots noindex,nofollow
 *  $meta_breadcrumb   - [['name' => '홈', 'url' => '/'], ...]
 *  $meta_jsonld       - 추가 JSON-LD 블록 (array 또는 array[])
 *  $meta_published_at - ISO8601 (article 타입)
 *  $meta_modified_at  - ISO8601 (article 타입)
 *  $meta_author       - 저자명 (article 타입)
 */

const SEO_SITE_NAME = 'Pokazone';
const SEO_SITE_DESC = '포켓몬 카드 거래 플랫폼 Pokazone - 안전한 검수 시스템과 실시간 시세로 카드와 미개봉 상자를 사고 팔고 교환하세요.';
const SEO_SITE_KEYS = '포켓몬, 포켓몬카드, TCG, 포켓몬 TCG, 포켓몬 카드 거래, 포켓몬 카드 시세, 포켓몬 카드 중고, 부스터박스, 미개봉상자, 리자몽, 피카츄, 수집, Pokazone';
const SEO_TWITTER   = '@pokazone';

/**
 * 현재 요청의 base URL (프로토콜 + 호스트) 반환
 */
function seo_base_url() {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
          || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)
        ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $proto . '://' . $host;
}

/**
 * 사이트 설치 경로(하위 디렉터리)를 반영한 웹 경로
 * DB·업로드에 저장된 값은 보통 '/uploads/...' 형태이며, 루트가 아닐 때 접두사가 필요합니다.
 */
function public_url($path) {
    if ($path === null || $path === '') return '';
    $path = (string)$path;
    if (preg_match('#^https?://#i', $path)) return $path;

    if (!function_exists('s3_public_url_for_path')) {
        $s3_lib = __DIR__ . '/_s3.php';
        if (is_file($s3_lib)) {
            require_once $s3_lib;
        }
    }
    $s3_url = function_exists('s3_public_url_for_path') ? s3_public_url_for_path($path) : '';
    if ($s3_url !== '') {
        return $s3_url;
    }

    global $site_path_prefix;
    $prefix = '';
    if (isset($site_path_prefix) && (string)$site_path_prefix !== '') {
        $prefix = '/' . trim((string)$site_path_prefix, '/');
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    return $prefix . $path;
}

/**
 * 상대 경로를 절대 URL 로 변환
 */
function seo_abs_url($path) {
    if (!$path) return '';
    if (preg_match('#^https?://#i', $path)) return $path;
    return seo_base_url() . public_url($path);
}

/**
 * 설명용 텍스트 정리 (HTML/개행 제거 + 길이 제한)
 */
function seo_clean_desc($str, $limit = 160) {
    $s = strip_tags((string)$str);
    $s = preg_replace('/\s+/u', ' ', $s);
    $s = trim($s);
    if (mb_strlen($s) > $limit) {
        $s = mb_substr($s, 0, $limit - 1) . '…';
    }
    return $s;
}

/**
 * 현재 요청의 canonical URL (쿼리스트링 일부 제거)
 */
function seo_canonical() {
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $parts = parse_url($uri);
    $path  = $parts['path'] ?? '/';
    $qs    = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $qs);
        // 추적 파라미터 / 비기능 파라미터 제거
        foreach (['utm_source','utm_medium','utm_campaign','utm_term','utm_content','fbclid','gclid','debug'] as $k) {
            unset($qs[$k]);
        }
    }
    $query = !empty($qs) ? '?' . http_build_query($qs) : '';
    return seo_base_url() . $path . $query;
}

/**
 * meta 태그들을 출력 (header.php 내에서 호출)
 */
function seo_render_meta(array $opts = []) {
    $site = SEO_SITE_NAME;

    $title       = $opts['title']       ?? '';
    $desc        = seo_clean_desc($opts['description'] ?? SEO_SITE_DESC);
    $keywords    = $opts['keywords']    ?? SEO_SITE_KEYS;
    $image       = $opts['image']       ?? '';
    $type        = $opts['type']        ?? 'website';
    $canonical   = $opts['canonical']   ?? seo_canonical();
    $noindex     = !empty($opts['noindex']);
    $published   = $opts['published_at'] ?? '';
    $modified    = $opts['modified_at']  ?? '';
    $author      = $opts['author']       ?? '';

    $full_title = $title !== ''
        ? htmlspecialchars($title) . ' | ' . $site
        : $site . ' | 포켓몬 카드 거래 플랫폼';

    $image_abs = $image ? seo_abs_url($image) : seo_abs_url('/assets/img/og-default.png');

    $h = function($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

    $out  = '';
    $out .= '<meta name="description" content="' . $h($desc) . '">' . "\n";
    $out .= '<meta name="keywords" content="' . $h($keywords) . '">' . "\n";
    $out .= '<meta name="author" content="' . $h($site) . '">' . "\n";
    $out .= '<meta name="theme-color" content="#ef4444">' . "\n";
    $out .= '<meta name="format-detection" content="telephone=no">' . "\n";
    $out .= '<meta name="robots" content="' . ($noindex ? 'noindex,nofollow' : 'index,follow,max-image-preview:large,max-snippet:-1,max-video-preview:-1') . '">' . "\n";
    $out .= '<link rel="canonical" href="' . $h($canonical) . '">' . "\n";

    // Open Graph
    $out .= '<meta property="og:site_name" content="' . $h($site) . '">' . "\n";
    $out .= '<meta property="og:type" content="' . $h($type) . '">' . "\n";
    $out .= '<meta property="og:title" content="' . $h($full_title) . '">' . "\n";
    $out .= '<meta property="og:description" content="' . $h($desc) . '">' . "\n";
    $out .= '<meta property="og:url" content="' . $h($canonical) . '">' . "\n";
    $out .= '<meta property="og:locale" content="ko_KR">' . "\n";
    if ($image_abs) {
        $out .= '<meta property="og:image" content="' . $h($image_abs) . '">' . "\n";
        $out .= '<meta property="og:image:alt" content="' . $h($full_title) . '">' . "\n";
    }
    if ($type === 'article') {
        if ($published) $out .= '<meta property="article:published_time" content="' . $h($published) . '">' . "\n";
        if ($modified)  $out .= '<meta property="article:modified_time" content="'  . $h($modified)  . '">' . "\n";
        if ($author)    $out .= '<meta property="article:author" content="'         . $h($author)    . '">' . "\n";
    }

    // Twitter Card
    $out .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
    $out .= '<meta name="twitter:site" content="' . $h(SEO_TWITTER) . '">' . "\n";
    $out .= '<meta name="twitter:title" content="' . $h($full_title) . '">' . "\n";
    $out .= '<meta name="twitter:description" content="' . $h($desc) . '">' . "\n";
    if ($image_abs) $out .= '<meta name="twitter:image" content="' . $h($image_abs) . '">' . "\n";

    echo $out;
}

/**
 * JSON-LD 출력 헬퍼
 */
function seo_render_jsonld($data) {
    if (!$data) return;
    $blocks = [];
    if (is_array($data) && isset($data[0]) && is_array($data[0])) {
        $blocks = $data;
    } else {
        $blocks = [$data];
    }
    foreach ($blocks as $b) {
        if (!is_array($b) || empty($b)) continue;
        echo '<script type="application/ld+json">' .
             json_encode($b, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) .
             '</script>' . "\n";
    }
}

/**
 * Breadcrumb -> JSON-LD 변환
 */
function seo_breadcrumb_jsonld(array $items) {
    if (empty($items)) return null;
    $list = [];
    foreach ($items as $i => $it) {
        $list[] = [
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'name'     => (string)($it['name'] ?? ''),
            'item'     => seo_abs_url((string)($it['url'] ?? '/')),
        ];
    }
    return [
        '@context'         => 'https://schema.org',
        '@type'            => 'BreadcrumbList',
        'itemListElement'  => $list,
    ];
}

/**
 * 사이트 공통 Organization/WebSite JSON-LD
 */
function seo_site_jsonld() {
    return [
        [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => SEO_SITE_NAME,
            'url'      => seo_base_url() . '/',
            'logo'     => seo_abs_url('/assets/img/logo.png'),
            'sameAs'   => [],
        ],
        [
            '@context'        => 'https://schema.org',
            '@type'           => 'WebSite',
            'name'            => SEO_SITE_NAME,
            'url'             => seo_base_url() . '/',
            'inLanguage'      => 'ko-KR',
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => seo_base_url() . '/page/shop.php?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ],
    ];
}
