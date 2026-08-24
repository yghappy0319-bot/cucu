<?
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once __DIR__ . '/_shop.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    shop_json(['ok' => false, 'msg' => 'POST만 허용됩니다.']);
}

$code = isset($_POST['code']) ? trim($_POST['code']) : '';
$회원 = shop_auth($code);
if (!$회원) {
    shop_json(['ok' => false, 'msg' => '인증코드가 올바르지 않습니다.']);
}

$gifticon_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$code_text = isset($_POST['code_text']) ? trim((string)$_POST['code_text']) : '';
if ($gifticon_id < 1 || $code_text === '') {
    shop_json(['ok' => false, 'msg' => '저장할 코드 정보가 올바르지 않습니다.']);
}

$result = shop_구매상품_추출코드_저장($gifticon_id, $회원['nick'], $code_text, $code);
shop_json([
    'ok'        => !empty($result['ok']),
    'msg'       => $result['msg'] ?? (!empty($result['ok']) ? '코드가 저장되었습니다.' : '코드 저장에 실패했습니다.'),
    'code_text' => $result['code_text'] ?? '',
]);
