<?
/**
 * @deprecated shop/_auto_price.php 로 통합됨 (겜냥·본방냥 동시 갱신)
 */
$q = [];
if (isset($_GET['format'])) {
    $q['format'] = trim((string)$_GET['format']);
}
$target = '/shop/_auto_price.php' . ($q ? ('?' . http_build_query($q)) : '');
header('Location: ' . $target, true, 302);
exit;
