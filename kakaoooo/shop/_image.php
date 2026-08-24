<?
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once __DIR__ . '/_shop.php';

$gifticon_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$code = shop_코드_해석();
$download = isset($_GET['download']) ? (int)$_GET['download'] : 0;

$회원 = shop_auth($code);
if (!$회원) {
    http_response_code(403);
    exit('인증이 필요합니다.');
}

$row = shop_이미지_열람가능($gifticon_id, $회원['nick'], $code);
if (!$row) {
    http_response_code(403);
    exit('구매자만 이미지를 볼 수 있습니다.');
}

$path = shop_이미지_물리경로($row['image_file']);
if ($path === '' || !is_file($path)) {
    http_response_code(404);
    exit('이미지를 찾을 수 없습니다.');
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$types = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
];
$mime = $types[$ext] ?? 'application/octet-stream';
$download_name = 'gifticon_' . $gifticon_id . '.' . ($ext !== '' ? $ext : 'jpg');

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, no-store');
if ($download === 1) {
    header('Content-Disposition: attachment; filename="' . $download_name . '"');
}
readfile($path);
exit;
