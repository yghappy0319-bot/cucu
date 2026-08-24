<?php
/**
 * 사이트 레이어 팝업 (footer include)
 */
require_once __DIR__ . '/../lib/_site_popup.php';

if (!empty($meta_noindex) || !empty($hide_site_popup)) {
    return;
}

$page_key = isset($page) ? (string) $page : '';
$__site_popups = site_popup_active_list($page_key);
if (empty($__site_popups)) {
    return;
}

$__popup_payload = [];
foreach ($__site_popups as $p) {
    $img = trim((string) ($p['pu_image'] ?? ''));
    $__popup_payload[] = [
        'id'        => (int) $p['pu_idx'],
        'title'     => (string) $p['pu_title'],
        'content'   => (string) ($p['pu_content'] ?? ''),
        'image'     => $img !== '' ? public_url($img) : '',
        'link'      => (string) ($p['pu_link_url'] ?? ''),
        'width'     => max(240, min(900, (int) ($p['pu_width'] ?? 400))),
        'hide_days' => max(1, min(30, (int) ($p['pu_hide_days'] ?? 1))),
    ];
}
?>
<div id="site-popup-root" class="site-popup-root" hidden aria-live="polite"></div>
<script>
window.__SITE_POPUPS__ = <?php echo json_encode($__popup_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<?php
$__pz_popup_js = dirname(__DIR__) . '/assets/js/site_popup.js';
$__pz_popup_v = is_readable($__pz_popup_js) ? ('?m=' . (string) filemtime($__pz_popup_js)) : '';
?>
<script src="/assets/js/site_popup.js<?php echo htmlspecialchars($__pz_popup_v, ENT_QUOTES, 'UTF-8'); ?>"></script>
