<?
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once __DIR__ . '/_shop.php';
shop_매입컬럼_보장();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    shop_json(['ok' => false, 'msg' => 'POST만 허용됩니다.']);
}

$code = isset($_POST['code']) ? trim($_POST['code']) : '';
$회원 = shop_회원_인증($code);

$gifticon_id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($gifticon_id < 1) {
    shop_json(['ok' => false, 'msg' => '상품 정보가 올바르지 않습니다.']);
}

$관리자 = (bool)shop_관리자_인증($code);
if (!shop_수정_허용($회원['nick'])) {
    shop_json(['ok' => false, 'msg' => shop_수정삭제_문의문구()]);
}
$상품 = shop_판매상품_단건($gifticon_id, $회원['nick'], true);
if (!$상품) {
    shop_json(['ok' => false, 'msg' => '판매중인 상품을 찾을 수 없습니다.']);
}
$판매자닉 = trim((string)($상품['seller'] ?? ''));
if ($판매자닉 === '') {
    $판매자닉 = $회원['nick'];
}
$관리자타인수정 = $판매자닉 !== $회원['nick'];
if (!shop_수정_가능($상품, $회원['nick'], $관리자타인수정)) {
    shop_json(['ok' => false, 'msg' => shop_수정삭제_문의문구()]);
}

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$face_value = isset($_POST['face_value']) ? (int)$_POST['face_value'] : 0;
$category = isset($_POST['category']) ? trim($_POST['category']) : 'cafe';
$expire = isset($_POST['expire']) ? trim($_POST['expire']) : '';

$카테고리목록 = shop_카테고리목록();
if (!isset($카테고리목록[$category])) {
    $category = 'other';
}

if ($name === '' || mb_strlen($name, 'UTF-8') > 120) {
    shop_json(['ok' => false, 'msg' => '상품명을 입력해주세요. (120자 이내)']);
}
if ($face_value < SHOP_최소권면가) {
    shop_json(['ok' => false, 'msg' => '권면가는 ' . number_format(SHOP_최소권면가) . '원 이상만 등록할 수 있습니다.']);
}
if ($face_value > 1000000) {
    shop_json(['ok' => false, 'msg' => '권면가는 100만원 이하만 등록할 수 있습니다.']);
}
if ($expire === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expire)) {
    shop_json(['ok' => false, 'msg' => '유효기간을 선택해주세요.']);
}
if (strtotime($expire) < strtotime(date('Y-m-d'))) {
    shop_json(['ok' => false, 'msg' => '유효기간은 오늘 이후여야 합니다.']);
}

$image_file = $상품['image_file'];
if (!empty($_FILES['image']['name'])) {
    $업로드 = shop_이미지_업로드($_FILES['image']);
    if (empty($업로드['ok'])) {
        shop_json(['ok' => false, 'msg' => $업로드['msg'] ?? '이미지 업로드 실패']);
    }
    $old_path = shop_이미지_물리경로($image_file);
    if ($old_path !== '' && is_file($old_path)) {
        @unlink($old_path);
    }
    $image_file = $업로드['file'];
}

$price_nyang = shop_냥_값(shop_원화_냥환산($face_value, $판매자닉));
if (shop_냥_비교($price_nyang, '1') < 0) {
    shop_json(['ok' => false, 'msg' => '환산된 냥 가격이 너무 작습니다.']);
}
$price_newpoint = shop_선매입_권면가_newpoint($face_value, $판매자닉);
if ($price_newpoint < 1) {
    shop_json(['ok' => false, 'msg' => '환산된 본방냥 가격이 너무 작습니다.']);
}

$name_esc = addslashes($name);
$category_esc = addslashes($category);
$expire_esc = addslashes($expire);
$image_esc = addslashes($image_file);
$emoji = shop_카테고리_이모지($category);
$emoji_esc = addslashes($emoji);
$where = "idx = {$gifticon_id} AND status = 'sale'";
if (!$관리자타인수정) {
    $닉_esc = addslashes($회원['nick']);
    $where .= " AND seller_nick = '{$닉_esc}'";
}

$sql = "UPDATE tb_gifticon SET
    name = '{$name_esc}',
    face_value = {$face_value},
    price_nyang = {$price_nyang},
    price_newpoint = {$price_newpoint},
    price_updated_at = NOW(),
    category = '{$category_esc}',
    emoji = '{$emoji_esc}',
    image_file = '{$image_esc}',
    expire_date = '{$expire_esc}'
    WHERE {$where} LIMIT 1";

$ok = db_query($sql);
if (!$ok) {
    shop_json(['ok' => false, 'msg' => '수정에 실패했습니다.']);
}

shop_회원_권면가총액_동기화($판매자닉);

shop_json([
    'ok'          => true,
    'msg'         => "수정 완료! {$name} (" . number_format($face_value) . "원 → " . number_format($price_nyang) . "냥)",
    'price_nyang' => $price_nyang,
    'price_newpoint' => $price_newpoint,
    'face_value'  => $face_value,
]);
