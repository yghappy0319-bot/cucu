<?
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once __DIR__ . '/_shop.php';
shop_매입컬럼_보장();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    shop_json(['ok' => false, 'msg' => 'POST만 허용됩니다.']);
}

$code = isset($_POST['code']) ? trim($_POST['code']) : '';
$회원 = shop_회원_인증($code);

$brand = isset($_POST['brand']) ? trim($_POST['brand']) : '';
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$face_value = isset($_POST['face_value']) ? (int)$_POST['face_value'] : 0;
$category = isset($_POST['category']) ? trim($_POST['category']) : 'cafe';
$expire = isset($_POST['expire']) ? trim($_POST['expire']) : '';

$카테고리목록 = shop_카테고리목록();
if (!isset($카테고리목록[$category])) {
    $category = 'other';
}

if (mb_strlen($brand, 'UTF-8') > 60) {
    shop_json(['ok' => false, 'msg' => '브랜드는 60자 이내로 입력해주세요.']);
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

$price_nyang = shop_냥_값(shop_원화_냥환산($face_value, $회원['nick']));
if (shop_냥_비교($price_nyang, '1') < 0) {
    shop_json(['ok' => false, 'msg' => '환산된 냥 가격이 너무 작습니다.']);
}
$price_newpoint = shop_선매입_권면가_newpoint($face_value, $회원['nick']);
if ($price_newpoint < 1) {
    shop_json(['ok' => false, 'msg' => '환산된 본방냥 가격이 너무 작습니다.']);
}

$files = shop_다중이미지_정규화($_FILES['images'] ?? null);
if (empty($files)) {
    shop_json(['ok' => false, 'msg' => '기프티콘 이미지를 첨부해주세요.']);
}
if (count($files) > 10) {
    shop_json(['ok' => false, 'msg' => '기프티콘 이미지는 최대 10개까지 첨부할 수 있습니다.']);
}

$업로드목록 = [];
foreach ($files as $file) {
    $업로드 = shop_이미지_업로드($file);
    if (empty($업로드['ok'])) {
        foreach ($업로드목록 as $saved) {
            $path = shop_이미지_물리경로($saved);
            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
        shop_json(['ok' => false, 'msg' => $업로드['msg'] ?? '이미지 업로드 실패']);
    }
    $업로드목록[] = $업로드['file'];
}

$닉 = addslashes($회원['nick']);
$brand_esc = addslashes($brand);
$name_esc = addslashes($name);
$category_esc = addslashes($category);
$expire_esc = addslashes($expire);
$emoji = shop_카테고리_이모지($category);
$emoji_esc = addslashes($emoji);

$등록수 = 0;
$첫_idx = 0;
$이미지수 = count($업로드목록);
$총권면가 = $face_value * $이미지수;
global $conn;

foreach ($업로드목록 as $image_file) {
    $image_esc = addslashes($image_file);

    $sql = "INSERT INTO tb_gifticon SET
        seller_nick = '{$닉}',
        brand = '{$brand_esc}',
        name = '{$name_esc}',
        face_value = {$face_value},
        price_nyang = {$price_nyang},
        price_newpoint = {$price_newpoint},
        price_updated_at = NOW(),
        category = '{$category_esc}',
        emoji = '{$emoji_esc}',
        image_file = '{$image_esc}',
        expire_date = '{$expire_esc}',
        status = 'sale',
        regdate = NOW()";

    $ok = db_query($sql);
    if (!$ok) {
        shop_json(['ok' => false, 'msg' => 'DB 등록 실패. tb_gifticon.image_file 컬럼이 있는지 확인해주세요.']);
    }

    $new_idx = (int)mysqli_insert_id($conn);
    if ($첫_idx < 1) {
        $첫_idx = $new_idx;
    }
    $등록수++;
}

if ($등록수 > 0) {
    shop_회원_권면가총액_동기화($회원['nick']);
    shop_등록알림_등록($회원['nick'], [
        'idx'         => $첫_idx,
        'name'        => $name,
        'face_value'  => $face_value,
        'price_nyang' => $price_nyang,
        'emoji'       => $emoji,
        'count'       => $등록수,
    ]);
}

$msg = $등록수 === 1
    ? "등록 완료! {$name} (".number_format($face_value)."원 → ".number_format($price_nyang)."냥)"
    : "등록 완료! {$name} {$등록수}건 (".number_format($face_value)."원 × {$등록수}장 = ".number_format($총권면가)."원 → ".number_format($price_nyang)."냥/건)";

shop_json([
    'ok'          => true,
    'msg'         => $msg,
    'count'       => $등록수,
    'price_nyang' => $price_nyang,
    'price_newpoint' => $price_newpoint,
    'face_value'  => $face_value,
    'total_face_value' => $총권면가,
    'eunchong'    => 0,
]);
