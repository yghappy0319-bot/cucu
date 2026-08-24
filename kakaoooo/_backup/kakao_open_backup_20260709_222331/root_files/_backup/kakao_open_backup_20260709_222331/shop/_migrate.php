<?
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once __DIR__ . '/_shop.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    shop_json(['ok' => false, 'msg' => 'POST만 허용됩니다.']);
}

$code = isset($_POST['code']) ? trim($_POST['code']) : '';
$회원 = shop_관리자_인증($code);
if (!$회원) {
    shop_json(['ok' => false, 'msg' => '관리자만 실행할 수 있습니다.']);
}

$result = shop_마이그레이션_실행();

shop_json([
    'ok'      => true,
    'msg'     => "마이그레이션 완료! {$result['updated']}건 동기화 · 겜냥 변경 {$result['updated_nyang']}건 · 본방냥 변경 {$result['updated_newpoint']}건",
    'total'   => $result['total'],
    'updated' => $result['updated'],
    'skipped' => $result['skipped'],
]);
