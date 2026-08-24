<?
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once __DIR__ . '/_shop.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    shop_json(['ok' => false, 'msg' => 'POST만 허용됩니다.']);
}

$code = isset($_POST['code']) ? trim($_POST['code']) : '';
$회원 = shop_회원_인증($code);

$gifticon_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($gifticon_id < 1) {
    shop_json(['ok' => false, 'msg' => '상품 정보가 올바르지 않습니다.']);
}

$type = isset($_POST['type']) ? trim((string)$_POST['type']) : 'sale';
if ($type === 'buy') {
    $result = shop_구매상품_삭제($gifticon_id, $회원['nick']);
} else {
    $result = shop_판매상품_삭제($gifticon_id, $회원['nick']);
}
if (empty($result['ok'])) {
    shop_json(['ok' => false, 'msg' => $result['msg'] ?? '삭제에 실패했습니다.']);
}

shop_json([
    'ok'  => true,
    'msg' => $result['msg'] ?? '삭제되었습니다.',
]);
