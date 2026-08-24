<?
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once __DIR__ . '/_shop.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    shop_json(['ok' => false, 'msg' => 'POST만 허용됩니다.']);
}

$code = isset($_POST['code']) ? trim($_POST['code']) : '';
$settle_type = isset($_POST['settle_type']) ? trim((string)$_POST['settle_type']) : 'point';
if ($settle_type !== 'newpoint') {
    $settle_type = 'point';
}

$회원 = shop_auth($code);
if (!$회원) {
    shop_json(['ok' => false, 'msg' => '인증코드가 올바르지 않습니다.']);
}

$닉 = $회원['nick'];
$닉_esc = addslashes($닉);
$정산대기 = shop_냥_값(shop_정산대기_합계($닉, $settle_type));
$건수 = shop_정산대기_건수($닉, $settle_type);

if (shop_냥_비교($정산대기, '1') < 0) {
    $통화명 = ($settle_type === 'newpoint') ? '본방냥' : '게임냥';
    shop_json(['ok' => false, 'msg' => "정산할 {$통화명} 판매 대금이 없습니다."]);
}

$실지급 = shop_냥_값(shop_정산_실지급액($정산대기));
$소멸 = shop_냥_값(shop_정산_소멸액($정산대기));

if ($settle_type === 'newpoint') {
    if (shop_냥_비교($실지급, '0') > 0) {
        db_query("UPDATE tb_member SET newpoint = newpoint + {$실지급} WHERE name = '{$닉_esc}' LIMIT 1");
    }
    db_query("UPDATE tb_gifticon SET settled = 1 WHERE seller_nick = '{$닉_esc}' AND status = 'sold' AND settled = 0 AND buy_currency = 'newpoint'");

    $새보유행 = db_select("SELECT newpoint FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    $새보유 = (float)($새보유행['newpoint'] ?? 0);

    $msg = "본방냥 정산 완료! {$건수}건 · " . shop_매입_지급_표시($실지급) . "냥 지급";
    if (shop_냥_비교($소멸, '0') > 0) {
        $msg .= " (수수료 " . shop_매입_지급_표시($소멸) . "냥 소멸)";
    }

    shop_json([
        'ok'         => true,
        'msg'        => $msg,
        'amount'     => $실지급,
        'fee'        => $소멸,
        'gross'      => $정산대기,
        'count'      => $건수,
        'newpoint'   => $새보유,
        'settle_type'=> 'newpoint',
    ]);
}

if (shop_냥_비교($실지급, '0') > 0) {
    db_query("UPDATE tb_member SET point = point + {$실지급} WHERE name = '{$닉_esc}' LIMIT 1");
}
db_query("UPDATE tb_gifticon SET settled = 1 WHERE seller_nick = '{$닉_esc}' AND status = 'sold' AND settled = 0 AND buy_currency = 'point'");

$잔여행 = db_select("SELECT CAST(IFNULL(point, 0) AS CHAR) AS point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
$새게임냥 = shop_냥_값($잔여행['point'] ?? 0);

$msg = "게임냥 정산 완료! {$건수}건 · " . shop_냥_표시($실지급) . "게임냥 지급";
if (shop_냥_비교($소멸, '0') > 0) {
    $msg .= " (수수료 " . shop_냥_표시($소멸) . "냥 소멸)";
}

shop_json([
    'ok'          => true,
    'msg'         => $msg,
    'amount'      => $실지급,
    'fee'         => $소멸,
    'gross'       => $정산대기,
    'count'       => $건수,
    'point'       => $새게임냥,
    'settle_type' => 'point',
]);
