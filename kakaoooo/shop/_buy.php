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

$강화검사 = shop_마켓구매_강화검사($회원);
if (empty($강화검사['ok'])) {
    shop_json(['ok' => false, 'msg' => $강화검사['msg'] ?? '무기 +20 이상만 마켓 구매가 가능해요.']);
}

$닉 = $회원['nick'];
$닉_esc = addslashes($닉);
shop_매입컬럼_보장();
$상품 = db_select("SELECT g.*, CAST(g.price_nyang AS CHAR) AS price_nyang
    FROM tb_gifticon g WHERE g.idx = {$gifticon_id} LIMIT 1");

if (empty($상품['idx'])) {
    shop_json(['ok' => false, 'msg' => '상품을 찾을 수 없습니다.']);
}
if (($상품['status'] ?? '') !== 'sale') {
    shop_json(['ok' => false, 'msg' => '이미 판매되었거나 구매할 수 없는 상품입니다.']);
}

$판매자 = trim((string)($상품['seller_nick'] ?? ''));
if ($판매자 !== '' && $판매자 === $닉) {
    shop_json(['ok' => false, 'msg' => '내가 올린 상품은 다시 구매할 수 없습니다.']);
}
$선매입 = (int)($상품['prebuy'] ?? 0) === 1;
global $conn;

// 구매 버튼마다 전체게임냥 반영 실시간 시세
$시세 = shop_상품_실시간구매가($상품, $닉, true);
if (empty($시세['ok'])) {
    shop_json(['ok' => false, 'msg' => $시세['msg'] ?? '상품 가격이 올바르지 않습니다.']);
}

$원가 = shop_냥_값($시세['list_price']);
$가격 = shop_냥_값($시세['pay_price']);

if (shop_냥_비교($가격, '1') < 0) {
    shop_json(['ok' => false, 'msg' => '상품 가격이 올바르지 않습니다.']);
}

// 잔액은 구매 직전 CAST로 재조회 (auth 시점 float 깨짐·동시차감 방지)
shop_스왑_함수_로드();
if (function_exists('tb_member_point_컬럼_보장')) {
    tb_member_point_컬럼_보장();
}
$잔액행 = db_select("SELECT CAST(IFNULL(point, 0) AS CHAR) AS point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
$보유 = shop_냥_값($잔액행['point'] ?? ($회원['point'] ?? 0));
if (shop_냥_비교($보유, $가격) < 0) {
    shop_json([
        'ok' => false,
        'msg' => '게임냥이 부족합니다. (보유: ' . shop_냥_표시($보유)
            . ' · 필요: ' . shop_냥_표시($가격) . ')',
        'list_nyang' => $원가,
        'paid_nyang' => $가격,
    ]);
}

$차감 = db_query("UPDATE tb_member SET point = point - {$가격} WHERE name = '{$닉_esc}' AND point >= {$가격} LIMIT 1");
if (!$차감 || !($conn instanceof mysqli) || mysqli_affected_rows($conn) < 1) {
    shop_json([
        'ok' => false,
        'msg' => '게임냥 차감에 실패했습니다. (보유: ' . shop_냥_표시($보유)
            . ' · 차감: ' . shop_냥_표시($가격) . ')',
    ]);
}

// 선매입: 전액 소멸 · 일반구매: 결제액 30% 판매자 즉시 환급 · 70% 소멸
// paid_nyang: 겜냥 실결제액(할인 반영) · price_nyang도 실결제액으로 맞춤
$판매자환급 = '0';
$소멸액 = $가격;
if ($선매입) {
    $정산완료 = 1;
} else if ($판매자 !== '') {
    $판매자환급 = shop_겜냥구매_판매자환급액($가격);
    $소멸액 = shop_겜냥구매_소멸액($가격);
    $정산완료 = 1;
} else {
    $정산완료 = 1;
}
$가격_sql = $가격;

$ok = db_query("UPDATE tb_gifticon SET
    status = 'sold',
    buyer_nick = '{$닉_esc}',
    settled = {$정산완료},
    paid_newpoint = 0,
    paid_nyang = {$가격_sql},
    price_nyang = {$가격_sql},
    buy_currency = 'point',
    sold_at = NOW(),
    price_updated_at = NOW()
    WHERE idx = {$gifticon_id} AND status = 'sale' LIMIT 1");
if (!$ok || !($conn instanceof mysqli) || mysqli_affected_rows($conn) < 1) {
    db_query("UPDATE tb_member SET point = point + {$가격} WHERE name = '{$닉_esc}' LIMIT 1");
    shop_json(['ok' => false, 'msg' => '구매 처리에 실패했습니다.']);
}

// 일반구매: 결제액 30% → 판매자 게임냥 즉시 지급
if (!$선매입 && $판매자 !== '' && shop_냥_비교($판매자환급, '1') >= 0) {
    $판매자_esc = addslashes($판매자);
    $환급_sql = $판매자환급;
    $환급ok = db_query("UPDATE tb_member SET point = point + {$환급_sql} WHERE name = '{$판매자_esc}' LIMIT 1");
    if (!$환급ok || !($conn instanceof mysqli) || mysqli_affected_rows($conn) < 1) {
        // 판매자 지급 실패 시 구매 롤백
        db_query("UPDATE tb_gifticon SET
            status = 'sale',
            buyer_nick = NULL,
            settled = 0,
            paid_nyang = 0,
            buy_currency = 'point',
            sold_at = NULL
            WHERE idx = {$gifticon_id} LIMIT 1");
        db_query("UPDATE tb_member SET point = point + {$가격} WHERE name = '{$닉_esc}' LIMIT 1");
        shop_json(['ok' => false, 'msg' => '판매자 게임냥 환급에 실패했습니다. 다시 시도해주세요.']);
    }
}

// 게임냥 결제액의 0.1% → config.선매입적립 누적
shop_선매입적립_반영($가격);

$상품['price_nyang'] = $가격;
shop_구매알림_등록($닉, $상품, 'point');

// 겜냥 소비 반영 → 나머지 판매중 상품 시세 동기화
$시세동기 = shop_판매중_실시간시세_동기화(true, $닉);

$환급퍼센트 = (int)round((float)SHOP_겜냥구매_판매자환급율 * 100);
if ($선매입) {
    $완료메시지 = '구매 완료! (선매입 상품 — 구매 냥 ' . shop_냥_전체표시($가격) . '게임냥 소멸)';
} else if (shop_냥_비교($판매자환급, '0') > 0) {
    $완료메시지 = '구매 완료! (겜냥 ' . shop_냥_전체표시($가격) . '냥 차감 · 판매자 '
        . shop_냥_전체표시($판매자환급) . "냥 환급({$환급퍼센트}%) · 나머지 소멸)";
} else {
    $완료메시지 = '구매 완료! (겜냥 ' . shop_냥_전체표시($가격) . '냥 차감 · 전액 소멸)';
}

$잔여행 = db_select("SELECT CAST(IFNULL(point, 0) AS CHAR) AS point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
$잔여 = shop_냥_값($잔여행['point'] ?? (function_exists('bcsub') ? bcsub($보유, $가격, 0) : '0'));

shop_json([
    'ok'          => true,
    'msg'         => $완료메시지,
    'point'       => $잔여,
    'gifticon_id' => $gifticon_id,
    'pay_type'    => 'point',
    'prebuy_burn' => $선매입,
    'self_buy'    => false,
    'paid_nyang'  => $가격,
    'list_nyang'  => $원가,
    'seller_refund_nyang' => $판매자환급,
    'burn_nyang'  => $소멸액,
    'market_prices' => $시세동기['items'] ?? [],
    'market_prices_updated' => (int)($시세동기['updated'] ?? 0),
]);
