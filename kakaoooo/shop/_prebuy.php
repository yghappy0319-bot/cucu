<?
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once __DIR__ . '/_shop.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    shop_json(['ok' => false, 'msg' => 'POST만 허용됩니다.']);
}

$code = isset($_POST['code']) ? trim($_POST['code']) : '';
$gifticon_id = isset($_POST['gifticon_id']) ? (int)$_POST['gifticon_id'] : 0;

$회원 = shop_회원_인증($code);
if (!shop_선매입_허용($회원['nick'] ?? '')) {
    shop_json(['ok' => false, 'msg' => '민호만 선매입할 수 있습니다.']);
}
if ($gifticon_id < 1) {
    shop_json(['ok' => false, 'msg' => '상품 정보가 올바르지 않습니다.']);
}

$result = shop_매입_실행($gifticon_id, $회원['nick']);
shop_json($result);
