<?
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once __DIR__ . '/_shop.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    shop_json(['ok' => false, 'msg' => 'POST만 허용됩니다.']);
}

$code = isset($_POST['code']) ? trim($_POST['code']) : '';
$gifticon_id = isset($_POST['gifticon_id']) ? (int)$_POST['gifticon_id'] : 0;
$pay_type = isset($_POST['pay_type']) ? trim((string)$_POST['pay_type']) : 'point';

$회원 = shop_회원_인증($code);
if ($gifticon_id < 1) {
    shop_json(['ok' => false, 'msg' => '상품 정보가 올바르지 않습니다.']);
}
if ($pay_type === 'newpoint') {
    shop_json(['ok' => false, 'msg' => '본냥으로는 구매할 수 없습니다. 게임냥으로 구매해주세요.']);
}

$닉 = $회원['nick'];
$닉_esc = addslashes($닉);
shop_매입컬럼_보장();
$상품 = db_select("SELECT * FROM tb_gifticon WHERE idx = {$gifticon_id} LIMIT 1");

if (empty($상품['idx'])) {
    shop_json(['ok' => false, 'msg' => '상품을 찾을 수 없습니다.']);
}
if (($상품['status'] ?? '') !== 'sale') {
    shop_json(['ok' => false, 'msg' => '이미 판매되었거나 구매할 수 없는 상품입니다.']);
}

$판매자 = trim((string)($상품['seller_nick'] ?? ''));
if ($판매자 === $닉) {
    shop_json(['ok' => false, 'msg' => '본인이 등록한 상품은 구매할 수 없습니다.']);
}

$선매입 = (int)($상품['prebuy'] ?? 0) === 1;
global $conn;

$가격 = shop_냥_값($상품['price_nyang'] ?? 0);
$보유 = shop_냥_값($회원['point'] ?? 0);
if (shop_냥_비교($보유, $가격) < 0) {
    shop_json(['ok' => false, 'msg' => '게임냥이 부족합니다. (필요: ' . shop_냥_전체표시($가격) . '게임냥)']);
}

$차감 = db_query("UPDATE tb_member SET point = point - {$가격} WHERE name = '{$닉_esc}' AND point >= {$가격} LIMIT 1");
if (!$차감 || !($conn instanceof mysqli) || mysqli_affected_rows($conn) < 1) {
    shop_json(['ok' => false, 'msg' => '게임냥 차감에 실패했습니다.']);
}

$정산완료 = $선매입 ? 1 : 0;

$ok = db_query("UPDATE tb_gifticon SET
    status = 'sold',
    buyer_nick = '{$닉_esc}',
    settled = {$정산완료},
    paid_newpoint = 0,
    buy_currency = 'point',
    sold_at = NOW()
    WHERE idx = {$gifticon_id} AND status = 'sale' LIMIT 1");
if (!$ok || !($conn instanceof mysqli) || mysqli_affected_rows($conn) < 1) {
    db_query("UPDATE tb_member SET point = point + {$가격} WHERE name = '{$닉_esc}' LIMIT 1");
    shop_json(['ok' => false, 'msg' => '구매 처리에 실패했습니다.']);
}

shop_구매알림_등록($닉, $상품, 'point');

$완료메시지 = '구매 완료! 구매내역에서 기프티콘 이미지를 확인하세요.';
if ($선매입) {
    $완료메시지 = '구매 완료! (선매입 상품 — 구매 냥 ' . shop_냥_전체표시($가격) . '게임냥 소멸)';
} else {
    $완료메시지 = '구매 완료! (겜냥 ' . shop_냥_전체표시($가격) . '냥 차감 · 판매자 게임냥 정산 대기)';
}

shop_json([
    'ok'          => true,
    'msg'         => $완료메시지,
    'point'       => function_exists('bcsub') ? bcsub($보유, $가격) : max(0, (int)$보유 - (int)$가격),
    'gifticon_id' => $gifticon_id,
    'pay_type'    => 'point',
    'prebuy_burn' => $선매입,
]);
