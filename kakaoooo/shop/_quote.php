<?
/**
 * 마켓 실시간 겜냥 시세 조회 (구매 모달·확인 직전)
 * POST: code, gifticon_id
 *        sync=1 이면 판매중 전체 시세도 함께 반환(+DB 동기화)
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
include_once __DIR__ . '/_shop.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    shop_json(['ok' => false, 'msg' => 'POST만 허용됩니다.']);
}

$code = isset($_POST['code']) ? trim($_POST['code']) : '';
$gifticon_id = isset($_POST['gifticon_id']) ? (int)$_POST['gifticon_id'] : 0;
$sync = !empty($_POST['sync']);

$회원 = shop_회원_인증($code);
if ($gifticon_id < 1) {
    shop_json(['ok' => false, 'msg' => '상품 정보가 올바르지 않습니다.']);
}

$닉 = $회원['nick'];
shop_매입컬럼_보장();
$상품 = db_select("SELECT g.*, CAST(g.price_nyang AS CHAR) AS price_nyang
    FROM tb_gifticon g WHERE g.idx = {$gifticon_id} LIMIT 1");

if (empty($상품['idx'])) {
    shop_json(['ok' => false, 'msg' => '상품을 찾을 수 없습니다.']);
}
if (($상품['status'] ?? '') !== 'sale') {
    shop_json(['ok' => false, 'msg' => '이미 판매되었거나 구매할 수 없는 상품입니다.']);
}

$시세 = shop_상품_실시간구매가($상품, $닉, true);
if (empty($시세['ok'])) {
    shop_json(['ok' => false, 'msg' => $시세['msg'] ?? '시세를 불러오지 못했습니다.']);
}

// 조회 시점에 해당 상품 DB가도 맞춰 둠 (표시·정산 일치)
$list = shop_냥_값($시세['list_price']);
$sale_list = shop_냥_값(shop_원화_냥환산((int)$시세['face_value'], (string)$시세['seller']));
if ($sale_list === '0') {
    $sale_list = $list;
}
if (!empty($시세['self_buy'])) {
    // 본인 재구매 표시가는 tax 반영, DB 등록가는 2.5% 판매등록가 유지
    @db_query("UPDATE tb_gifticon SET price_nyang = {$sale_list}, price_updated_at = NOW()
        WHERE idx = {$gifticon_id} AND status = 'sale' LIMIT 1");
} else {
    @db_query("UPDATE tb_gifticon SET price_nyang = {$list}, price_updated_at = NOW()
        WHERE idx = {$gifticon_id} AND status = 'sale' LIMIT 1");
}

$out = [
    'ok' => true,
    'gifticon_id' => $gifticon_id,
    'list_nyang' => $시세['list_price'],
    'pay_nyang' => $시세['pay_price'],
    'list_disp' => shop_냥_표시($시세['list_price']),
    'pay_disp' => shop_냥_표시($시세['pay_price']),
    'self_buy' => !empty($시세['self_buy']),
    'market_tax' => $시세['market_tax'],
    'market_tax_disp' => shop_market_tax_표시($시세['market_tax']),
    'face_value' => (int)$시세['face_value'],
    'discount_pct' => (int)($시세['gold_pct'] ?? 0),
    'bar_discount_pct' => (int)($시세['bar_discount_pct'] ?? 0),
    'gem_discount_pct' => (int)($시세['gem_discount_pct'] ?? 0),
    'gold_count' => (int)($시세['gold_count'] ?? 0),
    'discount_label' => (string)($시세['discount_label'] ?? ''),
];

if ($sync) {
    $시세동기 = shop_판매중_실시간시세_동기화(true, $닉);
    $out['market_prices'] = $시세동기['items'] ?? [];
    $out['market_prices_updated'] = (int)($시세동기['updated'] ?? 0);
    if (!empty($시세동기['discount'])) {
        $out['market_discount'] = $시세동기['discount'];
    }
}

shop_json($out);
