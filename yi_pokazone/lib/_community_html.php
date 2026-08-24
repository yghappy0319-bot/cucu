<?php
/**
 * 커뮤니티 게시글 HTML (Summernote 등) 허용 태그·속성 필터링
 */

/** libxml DOM 상수 — 일부 PHP-FPM 환경에서 DOMDocument 는 있으나 상수만 없는 경우 */
if (!defined('XML_ELEMENT_NODE')) {
    define('XML_ELEMENT_NODE', 1);
}
if (!defined('XML_TEXT_NODE')) {
    define('XML_TEXT_NODE', 3);
}
if (!defined('XML_CDATA_SECTION_NODE')) {
    define('XML_CDATA_SECTION_NODE', 4);
}
if (!defined('XML_DOCUMENT_NODE')) {
    define('XML_DOCUMENT_NODE', 9);
}
if (!defined('XML_DOCUMENT_FRAGMENT_NODE')) {
    define('XML_DOCUMENT_FRAGMENT_NODE', 11);
}

function community_dom_available(): bool
{
    return class_exists('DOMDocument') && defined('LIBXML_HTML_NOIMPLIED');
}

function community_stored_looks_like_html(string $s): bool
{
    return (bool)preg_match(
        '/<\/?(p|div|br|img|strong|ul|ol|li|h[1-6]|blockquote|a|span|b|i|em|u|s|strike|del|pre|code)\b/i',
        $s
    );
}

function community_editor_is_effectively_empty(string $html): bool
{
    $plain = trim(strip_tags($html));
    if ($plain !== '') {
        return false;
    }
    return !preg_match('/<img\b[^>]*\bsrc\s*=/i', $html);
}

function community_editor_img_src_allowed(string $src): bool
{
    $canonical = community_editor_img_src_canonical($src);

    return $canonical !== ''
        && strpos($canonical, '/uploads/community/') === 0
        && strpos($canonical, '//') === false;
}

/**
 * 업로드·에디터에서 /{prefix}/uploads/community/ 형태일 수 있어 DB에는 /uploads/community/ 로만 저장
 */
function community_editor_img_src_canonical(string $src): string
{
    $src = trim($src);
    if ($src === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $src)) {
        if (!function_exists('s3_config')) {
            $s3_lib = __DIR__ . '/_s3.php';
            if (is_file($s3_lib)) {
                require_once $s3_lib;
            }
        }
        if (function_exists('s3_config')) {
            $base = rtrim((string) (s3_config()['public_base_url'] ?? ''), '/');
            if ($base !== '' && stripos($src, $base) === 0) {
                $path = substr($src, strlen($base));
                if ($path === '' || $path[0] !== '/') {
                    $path = '/' . ltrim($path, '/');
                }
                if (strpos($path, '/uploads/community/') === 0) {
                    return $path;
                }
            }
        }

        return '';
    }
    if (preg_match('#^//#i', $src)) {
        return '';
    }

    if (strpos($src, '/uploads/community/') === 0) {
        return $src;
    }

    global $site_path_prefix;
    $pre = isset($site_path_prefix) ? trim((string) $site_path_prefix, '/') : '';
    if ($pre !== '') {
        $prefix_path = '/' . $pre . '/uploads/community/';
        if (strpos($src, $prefix_path) === 0) {
            return '/uploads/community/' . substr($src, strlen($prefix_path));
        }
    }

    return '';
}

function community_editor_href_allowed(string $href): bool
{
    $href = trim($href);
    if ($href === '' || $href === '#') {
        return true;
    }
    if (stripos($href, 'javascript:') === 0 || stripos($href, 'data:') === 0) {
        return false;
    }
    if (preg_match('#^(https?:)?//#i', $href)) {
        return true;
    }

    return $href[0] === '/' && strpos($href, '//') !== 0;
}

/** @return array<string, string[]> */
function community_allowed_html_map(): array
{
    static $allowed = [
        'p'          => [],
        'br'         => [],
        'strong'     => [],
        'b'          => [],
        'em'         => [],
        'i'          => [],
        'u'          => [],
        's'          => [],
        'strike'     => [],
        'del'        => [],
        'ul'         => [],
        'ol'         => [],
        'li'         => [],
        'h1'         => [],
        'h2'         => [],
        'h3'         => [],
        'h4'         => [],
        'h5'         => [],
        'h6'         => [],
        'blockquote' => [],
        'span'       => [],
        'div'        => [],
        'pre'        => [],
        'code'       => [],
        'a'          => ['href', 'title', 'target', 'rel'],
        'img'        => ['src', 'alt', 'title', 'width', 'height'],
    ];

    return $allowed;
}

function community_dom_unwrap(DOMElement $el): void
{
    $p = $el->parentNode;
    if (!$p) {
        return;
    }
    while ($el->firstChild) {
        $p->insertBefore($el->firstChild, $el);
    }
    $p->removeChild($el);
}

function community_dom_clean_attrs(DOMElement $el, string $tag, array $allowedAttrNames): void
{
    $allowedLower = [];
    foreach ($allowedAttrNames as $n) {
        $allowedLower[strtolower((string)$n)] = true;
    }

    $toRemove = [];
    foreach ($el->attributes as $attr) {
        $fn = strtolower($attr->nodeName);
        if (strncmp($fn, 'on', 2) === 0 || $fn === 'xmlns' || $fn === 'style') {
            $toRemove[] = $attr->nodeName;

            continue;
        }
        if (empty($allowedAttrNames) || !isset($allowedLower[$fn])) {
            $toRemove[] = $attr->nodeName;
        }
    }
    foreach ($toRemove as $n) {
        $el->removeAttribute($n);
    }

    if ($tag === 'a') {
        $href = $el->getAttribute('href');
        if ($href !== '' && !community_editor_href_allowed($href)) {
            community_dom_unwrap($el);

            return;
        }
        if (strtolower($el->getAttribute('target')) === '_blank') {
            $parts = [];
            foreach (preg_split('/\s+/u', trim((string)$el->getAttribute('rel')), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $r) {
                $parts[strtolower((string)$r)] = true;
            }
            $parts['noopener']   = true;
            $parts['noreferrer'] = true;
            $el->setAttribute('rel', implode(' ', array_keys($parts)));
        }
    }

    if ($tag === 'img') {
        $src = $el->getAttribute('src');
        $canon = community_editor_img_src_canonical($src);
        if ($canon === '' || !community_editor_img_src_allowed($src)) {
            $el->parentNode && $el->parentNode->removeChild($el);

            return;
        }
        $el->setAttribute('src', $canon);
        foreach (['width', 'height'] as $dim) {
            $v = (string)$el->getAttribute($dim);
            if ($v !== '' && !preg_match('/^\d+(?:px)?$/i', $v)) {
                $el->removeAttribute($dim);
            }
        }
    }
}

function community_dom_clean(DOMNode $node, array $allowed): void
{
    if ($node->nodeType === XML_DOCUMENT_NODE || $node->nodeType === XML_DOCUMENT_FRAGMENT_NODE) {
        for ($ch = $node->firstChild; $ch;) {
            $nnext = $ch->nextSibling;
            community_dom_clean($ch, $allowed);
            $ch = $nnext;
        }

        return;
    }

    if ($node->nodeType !== XML_ELEMENT_NODE) {
        if ($node->nodeType !== XML_TEXT_NODE && $node->nodeType !== XML_CDATA_SECTION_NODE) {
            $p = $node->parentNode;
            if ($p) {
                $p->removeChild($node);
            }
        }

        return;
    }

    /** @var DOMElement $el */
    $el  = $node;
    $tag = strtolower($el->tagName);

    $ch = $el->firstChild;
    while ($ch) {
        $nnext = $ch->nextSibling;
        community_dom_clean($ch, $allowed);
        $ch = $nnext;
    }

    if (!isset($allowed[$tag])) {
        community_dom_unwrap($el);

        return;
    }

    community_dom_clean_attrs($el, $tag, $allowed[$tag]);
}

/**
 * DOM 확장(php-xml) 미설치 서버용 간이 HTML 정화
 */
function community_sanitize_html_fallback(string $html): string
{
    $allowed = community_allowed_html_map();
    $tagList = '<' . implode('><', array_keys($allowed)) . '>';
    $out     = strip_tags($html, $tagList);

    $out = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/iu', '', $out);
    $out = preg_replace('/\s+style\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]*)/iu', '', $out);

    $out = preg_replace_callback(
        '/\s(href|src)\s*=\s*("([^"]*)"|\'([^\']*)\')/iu',
        static function (array $m): string {
            $attr = strtolower($m[1]);
            $val  = $m[3] !== '' ? $m[3] : ($m[4] ?? '');
            if ($attr === 'src') {
                $canon = community_editor_img_src_canonical($val);
                if ($canon === '' || !community_editor_img_src_allowed($val)) {
                    return ' src=""';
                }

                return ' src="' . htmlspecialchars($canon, ENT_QUOTES, 'UTF-8') . '"';
            }
            if (!community_editor_href_allowed($val)) {
                return ' href="#"';
            }

            return ' href="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"';
        },
        $out
    );

    return trim($out);
}

function community_sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    if (!community_dom_available()) {
        return community_sanitize_html_fallback($html);
    }

    $allowed = community_allowed_html_map();

    libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');
    $wrapId = '__pz_co_san_wrap';
    $snippet = '<div id="' . $wrapId . '">' . $html . '</div>';
    @$doc->loadHTML(
        '<?xml encoding="UTF-8">' . $snippet,
        LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
    );
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);
    $nodes = $xpath->query('//*[@id="' . $wrapId . '"]');

    /** @var DOMElement|null $root */
    $root = ($nodes !== false && $nodes->length > 0)
        ? $nodes->item(0)
        : null;

    if (!$root instanceof DOMElement) {
        return htmlspecialchars(strip_tags($html), ENT_QUOTES, 'UTF-8');
    }

    community_dom_clean($root, $allowed);

    $outHtml = '';
    foreach (iterator_to_array($root->childNodes) as $child) {
        $outHtml .= $doc->saveHTML($child);
    }

    return trim($outHtml);
}

/** 하위 디렉터리 설치(public_url 접두사) 시 본문의 상대 경로 보정 */
function community_html_apply_public_urls(string $html): string
{
    if ($html === '' || !function_exists('public_url')) {
        return $html;
    }

    return (string)preg_replace_callback(
        '#\s(href|src)=(["\'])(/[^"\']+)\2#i',
        static function ($m) {
            $attr = strtolower($m[1]);
            $q    = $m[2];
            $path = $m[3];

            return ' ' . $attr . '=' . $q . htmlspecialchars(public_url($path), ENT_QUOTES, 'UTF-8') . $q;
        },
        $html
    );
}

/** 저장된 본문을 화면에 출력할 HTML로 변환 */
function community_render_post_body(string $stored): string
{
    $stored = trim($stored);
    if ($stored === '') {
        return '';
    }

    if (community_stored_looks_like_html($stored)) {
        return community_html_apply_public_urls(community_sanitize_html($stored));
    }

    return nl2br(htmlspecialchars($stored, ENT_QUOTES, 'UTF-8'));
}
