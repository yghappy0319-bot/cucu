<?php
/**
 * 색표 고정가 매매 API (본방냥)
 * POST action: buy | list | unlist | claim | buy_item | wear | lock | unlock
 */
include_once $_SERVER['DOCUMENT_ROOT'] . '/lib/_function.php';
if (!function_exists('냥_정수문자열')) {
    include_once $_SERVER['DOCUMENT_ROOT'] . '/api/function.php';
}
include_once __DIR__ . '/_color_market_lib.php';

cm_테이블보장();
cm_초과판매_정리();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    cm_json(['ok' => false, 'msg' => 'POST만 허용됩니다.']);
}

$action = trim((string)($_POST['action'] ?? ''));
$code = trim((string)($_POST['code'] ?? ($_POST['wallet_code'] ?? '')));
$color_num = (int)($_POST['color_num'] ?? 0);
$price_raw = trim((string)($_POST['price'] ?? ''));
if (function_exists('냥_금액_파싱_문자열')) {
    $price_in = 냥_금액_파싱_문자열($price_raw);
} else {
    $price_in = cm_냥($price_raw);
}

$회원 = cm_회원필수($code);
$닉 = $회원['nick'];
$닉_esc = addslashes($닉);
$준호 = cm_준호인가($닉);
$내본방 = cm_냥($회원['newpoint'] ?? 0);
$본방총합 = cm_본방냥총합();

if ($color_num < 1 || $color_num > 45) {
    cm_json(['ok' => false, 'msg' => '색번호는 1~45만 가능해요.']);
}

$색 = cm_색행($color_num);
if (!$색) {
    cm_json(['ok' => false, 'msg' => '색 정보를 찾을 수 없어요.']);
}

$wearers = $색['wearers'] ?? [];
$내색 = cm_내색인가($닉, $색['owner_nick'], $wearers);
$잠김 = ((int)($색['trade_lock'] ?? 0) === 1);

if (function_exists('cm_예약색인가') && cm_예약색인가($color_num)
    && in_array($action, ['claim', 'buy', 'list', 'unlist', 'wear', 'lock', 'unlock'], true)) {
    $라벨 = function_exists('cm_예약색라벨') ? cm_예약색라벨() : '불가';
    cm_json(['ok' => false, 'msg' => "#1 색은 {$라벨} 예약색이에요. 장터에서 사고팔기·선점할 수 없어요."]);
}

global $conn;

$잔액응답 = function () use ($회원, $닉_esc) {
    $row = db_select("SELECT CAST(IFNULL(newpoint,0) AS CHAR) AS newpoint FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    $np = $row['newpoint'] ?? ($회원['newpoint'] ?? 0);
    if (function_exists('newpoint양도가능')) {
        $np = (string)newpoint양도가능($np);
    } else {
        $np = cm_냥((string)(int)floor((float)$np));
    }
    return [
        'newpoint' => cm_냥($np),
        'point_disp' => cm_냥표시($np),
        // 하위호환
        'point' => cm_냥($np),
    ];
};

if ($action === 'lock' || $action === 'unlock') {
    if (!$준호) {
        cm_json(['ok' => false, 'msg' => '민호만 구매불가 설정할 수 있어요.']);
    }
    $lock = ($action === 'lock') ? 1 : 0;
    db_query("UPDATE tb_color_market SET trade_lock = {$lock}, updated_at = NOW() WHERE color_num = {$color_num} LIMIT 1");
    if ($lock === 1) {
        db_query("UPDATE tb_color_market SET listed = 0, price = 0 WHERE color_num = {$color_num} AND listed = 1 LIMIT 1");
    }
    cm_로그($action, $color_num, $닉, '', 0, 0);
    $msg = $lock
        ? "#{$color_num} 색을 구매불가로 지정했어요. (사고팔기 불가)"
        : "#{$color_num} 색 구매불가를 해제했어요.";
    cm_json(array_merge([
        'ok'  => true,
        'msg' => $msg,
        'item' => cm_색행($color_num),
        'np_total' => $본방총합,
        'np_total_disp' => cm_냥표시($본방총합),
    ], $잔액응답()));
}

if ($action === 'list') {
    if ($잠김) {
        cm_json(['ok' => false, 'msg' => '구매불가 색은 판매 등록할 수 없어요.']);
    }
    $무주인 = ($색['owner_nick'] === '' && empty($wearers));
    if ($무주인) {
        if (!$준호) {
            cm_json(['ok' => false, 'msg' => '무주인 색 판매가 등록은 민호만 가능해요.']);
        }
    } elseif (!$내색) {
        cm_json(['ok' => false, 'msg' => '내 색(공커 포함)만 판매 등록할 수 있어요.']);
    }
    if (cm_비교($price_in, COLOR_MARKET_MIN_PRICE) < 0) {
        cm_json(['ok' => false, 'msg' => '최소 판매가는 ' . cm_냥표시(COLOR_MARKET_MIN_PRICE) . '이에요.']);
    }
    if (cm_비교($본방총합, '0') > 0 && cm_비교($price_in, $본방총합) > 0) {
        cm_json(['ok' => false, 'msg' => '판매가는 본방냥 총합(' . cm_냥표시($본방총합) . ')을 넘을 수 없어요.']);
    }
    $price_sql = $price_in;
    // 무주인: owner 비워 두고 가격만 등록 · 구매 시 구매자 닉으로 지정
    $owner_esc = $무주인
        ? ''
        : addslashes($색['owner_nick'] !== '' ? $색['owner_nick'] : $닉);
    db_query("UPDATE tb_color_market SET owner_nick = '{$owner_esc}', price = {$price_sql}, listed = 1, updated_at = NOW()
        WHERE color_num = {$color_num} AND trade_lock = 0 LIMIT 1");
    if (cm_affected() < 1) {
        cm_json(['ok' => false, 'msg' => '판매 등록에 실패했어요.']);
    }
    cm_로그('list', $color_num, $닉, '', $price_in, 0);
    $msg = $무주인
        ? "#{$color_num} 무주인 색을 " . cm_냥표시($price_in) . "에 올렸어요. (구매 시 구매자 소유)"
        : "#{$color_num} 색을 " . cm_냥표시($price_in) . "에 올렸어요.";
    cm_json(array_merge([
        'ok'  => true,
        'msg' => $msg,
        'item' => cm_색행($color_num),
        'np_total' => $본방총합,
        'np_total_disp' => cm_냥표시($본방총합),
    ], $잔액응답()));
}

if ($action === 'unlist') {
    $무주인 = ($색['owner_nick'] === '' && empty($wearers));
    if (!$내색 && !($준호 && $무주인)) {
        cm_json(['ok' => false, 'msg' => '내 색(공커 포함)만 판매 철회할 수 있어요.']);
    }
    db_query("UPDATE tb_color_market SET listed = 0, price = 0, updated_at = NOW()
        WHERE color_num = {$color_num} LIMIT 1");
    if (cm_affected() < 1) {
        cm_json(['ok' => false, 'msg' => '판매 철회에 실패했어요.']);
    }
    cm_로그('unlist', $color_num, $닉, '', 0, 0);
    cm_json(array_merge([
        'ok'  => true,
        'msg' => "#{$color_num} 색 판매를 내렸어요.",
        'item' => cm_색행($color_num),
    ], $잔액응답()));
}

if ($action === 'claim') {
    if ($잠김) {
        cm_json(['ok' => false, 'msg' => '구매불가 색은 선점할 수 없어요.']);
    }
    if ($색['owner_nick'] !== '' || !empty($wearers)) {
        cm_json(['ok' => false, 'msg' => '이미 주인이 있는 색이에요.']);
    }
    if ((int)$색['listed'] === 1 && cm_비교($색['price'], '1') >= 0) {
        cm_json(['ok' => false, 'msg' => '판매가가 등록된 무주인 색은 구매로만 가져갈 수 있어요.']);
    }

    $claimItem = COLOR_MARKET_CLAIM_ITEM;
    cm_가방로드();
    if (!function_exists('item_bag_sub_nick')) {
        cm_json(['ok' => false, 'msg' => '가방 기능을 불러올 수 없어요.']);
    }
    $보유 = cm_색변_보유($닉);
    $자동구매 = false;
    $구매시세 = '';
    if ($보유 < 1) {
        $quote = cm_색변_시세정보();
        $구매 = cm_색변_상점구매($회원);
        if (empty($구매['ok'])) {
            $시세안내 = !empty($quote['ok'])
                ? ("\n현재 {$claimItem} 시세: " . ($quote['total_disp'] ?? '-'))
                : '';
            cm_json([
                'ok' => false,
                'need_item' => true,
                'item_qty' => 0,
                'quote' => $quote,
                'msg' => trim((string)($구매['msg'] ?? "{$claimItem} 자동 구매에 실패했어요.")) . $시세안내,
            ]);
        }
        $자동구매 = true;
        $구매시세 = !empty($quote['ok']) ? (string)($quote['total_disp'] ?? '') : '';
        $보유 = cm_색변_보유($닉);
        if ($보유 < 1) {
            cm_json(['ok' => false, 'msg' => "{$claimItem} 구매는 됐지만 가방에서 찾을 수 없어요. 잠시 후 다시 선점해 주세요."]);
        }
    }
    $차감 = item_bag_sub_nick($닉, $claimItem, 1);
    if (empty($차감['ok'])) {
        cm_json(['ok' => false, 'msg' => '❌ ' . trim((string)($차감['msg'] ?? "{$claimItem} 아이템이 없어요."))]);
    }
    if (function_exists('아이템사용_시세하락')) {
        아이템사용_시세하락($claimItem, 1);
    }

    db_query("UPDATE tb_color_market SET owner_nick = '{$닉_esc}', price = 0, listed = 0, updated_at = NOW()
        WHERE color_num = {$color_num} AND owner_nick = '' AND listed = 0 AND trade_lock = 0 LIMIT 1");
    if (cm_affected() < 1) {
        // 선점 실패 시 아이템 환불
        if (function_exists('item_bag_add_nick')) {
            item_bag_add_nick($닉, $claimItem, 1);
        }
        cm_json(['ok' => false, 'msg' => '선점 실패! 다른 친구가 먼저 가져갔거나 판매중/구매불가 색이에요.']);
    }
    db_query("UPDATE tb_member SET num = {$color_num} WHERE name = '{$닉_esc}' LIMIT 1");
    cm_로그('claim', $color_num, '', $닉, 0, 0);
    $남은 = cm_색변_보유($닉);
    $선점문구 = "#{$color_num} 색을 선점했어요! ({$claimItem} 1개 사용 · 남은 {$남은}개)";
    if ($자동구매) {
        $선점문구 = $구매시세 !== ''
            ? "게임냥 {$구매시세}으로 {$claimItem} 1개 구매 후 #{$color_num} 색을 선점했어요! (남은 {$남은}개)"
            : "게임냥으로 {$claimItem} 1개 구매 후 #{$color_num} 색을 선점했어요! (남은 {$남은}개)";
    }
    cm_json(array_merge([
        'ok'  => true,
        'msg' => $선점문구,
        'item' => cm_색행($color_num),
        'my_num' => $color_num,
        'item_qty' => $남은,
        'bought_item' => $자동구매,
        'reload' => true,
    ], $잔액응답()));
}

if ($action === 'buy_item') {
    // 색변 아이템 1개 주식 구매 (게임냥) — 빈색 선점용
    $claimItem = COLOR_MARKET_CLAIM_ITEM;
    $결과 = cm_색변_상점구매($회원);
    if (empty($결과['ok'])) {
        cm_json([
            'ok' => false,
            'msg' => (string)($결과['msg'] ?? '구매 실패'),
            'quote' => cm_색변_시세정보(),
            'item_qty' => cm_색변_보유($닉),
        ]);
    }
    $남은 = cm_색변_보유($닉);
    $quote = cm_색변_시세정보();
    $pointRow = db_select("SELECT CAST(IFNULL(point,0) AS CHAR) AS point FROM tb_member WHERE name = '{$닉_esc}' LIMIT 1");
    cm_json(array_merge([
        'ok' => true,
        'msg' => (string)($결과['msg'] ?? "{$claimItem} 1개 구매 완료"),
        'item_qty' => $남은,
        'quote' => $quote,
        'game_point' => cm_냥($pointRow['point'] ?? ($결과['point'] ?? 0)),
        'reload' => true,
    ], $잔액응답()));
}

if ($action === 'wear') {
    if (!$내색) {
        cm_json(['ok' => false, 'msg' => '소유한 색(공커 포함)만 착용할 수 있어요.']);
    }
    db_query("UPDATE tb_member SET num = {$color_num} WHERE name = '{$닉_esc}' LIMIT 1");
    cm_json(array_merge([
        'ok'  => true,
        'msg' => "#{$color_num} 색을 프로필에 착용했어요.",
        'item' => cm_색행($color_num),
        'my_num' => $color_num,
    ], $잔액응답()));
}

if ($action === 'buy') {
    if ($잠김) {
        cm_json(['ok' => false, 'msg' => '구매불가 색은 살 수 없어요.']);
    }
    $무주인매매 = ($색['owner_nick'] === '' && empty($wearers));
    if ($무주인매매) {
        if ((int)$색['listed'] !== 1 || cm_비교($색['price'], '1') < 0) {
            cm_json(['ok' => false, 'msg' => '무주인 색은 선점(무료)으로 가져가세요.']);
        }
    } elseif ($내색) {
        cm_json(['ok' => false, 'msg' => '내 색(공커 포함)은 구매할 수 없어요.']);
    }
    if ((int)$색['listed'] !== 1 || cm_비교($색['price'], '1') < 0) {
        cm_json(['ok' => false, 'msg' => '판매중이 아닌 색이에요.']);
    }

    $price = cm_냥($색['price']);
    if (cm_비교($본방총합, '0') > 0 && cm_비교($price, $본방총합) > 0) {
        cm_json(['ok' => false, 'msg' => '판매가가 본방냥 총합을 초과해 거래할 수 없어요.']);
    }

    $fee = $무주인매매 ? '0' : cm_수수료($price);
    $seller_get = function_exists('bcsub') ? bcsub($price, $fee, 0) : (string)max(0, (int)$price - (int)$fee);
    $seller_get = cm_냥($seller_get);
    $seller = $색['owner_nick'] !== '' ? $색['owner_nick'] : ($wearers[0] ?? '');

    $수령자들 = $무주인매매 ? [] : cm_표시닉들($색['owner_nick'], $wearers);
    if (!$무주인매매) {
        if (empty($수령자들)) {
            $수령자들 = [$seller];
        }
        $수령자들 = array_values(array_unique(array_filter($수령자들)));
    }

    if (cm_비교($내본방, $price) < 0) {
        cm_json(['ok' => false, 'msg' => '본방냥이 부족해요. (필요: ' . cm_냥표시($price) . ' / 보유: ' . cm_냥표시($내본방) . ')']);
    }

    // 본방냥 차감 (정수 비교)
    db_query("UPDATE tb_member SET newpoint = newpoint - {$price}
        WHERE name = '{$닉_esc}' AND FLOOR(newpoint) >= {$price} LIMIT 1");
    if (cm_affected() < 1) {
        cm_json(['ok' => false, 'msg' => '본방냥 차감에 실패했어요.']);
    }

    // 구매자 닉으로 소유권 지정
    $owner_cond = $무주인매매 ? "AND owner_nick = ''" : '';
    db_query("UPDATE tb_color_market
        SET owner_nick = '{$닉_esc}', price = 0, listed = 0, updated_at = NOW()
        WHERE color_num = {$color_num} AND listed = 1 AND price = {$price} AND trade_lock = 0 {$owner_cond}
        LIMIT 1");
    if (cm_affected() < 1) {
        db_query("UPDATE tb_member SET newpoint = newpoint + {$price} WHERE name = '{$닉_esc}' LIMIT 1");
        cm_json(['ok' => false, 'msg' => '거래 실패! 이미 팔렸거나 구매불가로 막혔어요.']);
    }

    $cnt = count($수령자들);
    if ($무주인매매) {
        // 무주인 매매: 대금 전액 → 금고
        if (cm_비교($price, '0') > 0) {
            db_query("UPDATE config SET tax = tax + {$price}");
        }
        $fee = $price;
        $seller_get = '0';
    } elseif ($cnt >= 1 && cm_비교($seller_get, '0') > 0) {
        if ($cnt === 1) {
            $one_esc = addslashes($수령자들[0]);
            db_query("UPDATE tb_member SET newpoint = newpoint + {$seller_get} WHERE name = '{$one_esc}' LIMIT 1");
            if (function_exists('지급로그')) {
                지급로그('색판매', $수령자들[0], $닉, $fee, $seller_get);
            }
        } else {
            $half = function_exists('bcdiv') ? bcdiv($seller_get, (string)$cnt, 0) : (string)(int)floor((float)$seller_get / $cnt);
            $half = cm_냥($half);
            $paid = '0';
            foreach ($수령자들 as $i => $recv) {
                $recv_esc = addslashes($recv);
                if ($i === $cnt - 1 && function_exists('bcsub')) {
                    $share = bcsub($seller_get, $paid, 0);
                } else {
                    $share = $half;
                    $paid = function_exists('bcadd') ? bcadd($paid, $half, 0) : (string)((int)$paid + (int)$half);
                }
                $share = cm_냥($share);
                if (cm_비교($share, '0') > 0) {
                    db_query("UPDATE tb_member SET newpoint = newpoint + {$share} WHERE name = '{$recv_esc}' LIMIT 1");
                    if (function_exists('지급로그')) {
                        지급로그('색판매', $recv, $닉, 0, $share);
                    }
                }
            }
        }
        // 수수료 → 금고(tax) — 본방냥 양도와 동일 처리
        if (cm_비교($fee, '0') > 0) {
            db_query("UPDATE config SET tax = tax + {$fee}");
        }
    } elseif (cm_비교($fee, '0') > 0) {
        db_query("UPDATE config SET tax = tax + {$fee}");
    }

    db_query("UPDATE tb_member SET num = 0 WHERE num = {$color_num} AND status != 1");
    db_query("UPDATE tb_member SET num = {$color_num} WHERE name = '{$닉_esc}' LIMIT 1");

    cm_로그('buy', $color_num, $무주인매매 ? '(무주인)' : implode(',', $수령자들), $닉, $price, $fee);

    if (function_exists('지급로그')) {
        지급로그('색구매', $닉, $무주인매매 ? '금고' : $seller, $fee, '-' . $price);
    }

    if ($무주인매매) {
        $extraMsg = ' · 대금 전액 → 금고 · 주인이 되었어요';
    } else {
        $feeMsg = cm_비교($fee, '0') > 0 ? ' (수수료 ' . cm_냥표시($fee) . ' → 금고)' : '';
        $공커Msg = $cnt >= 2 ? ' · 공커 ' . $cnt . '명에게 분배' : '';
        $extraMsg = $feeMsg . $공커Msg;
    }

    cm_json(array_merge([
        'ok'  => true,
        'msg' => "#{$color_num} 색을 " . cm_냥표시($price) . "에 구매했어요!{$extraMsg}",
        'item' => cm_색행($color_num),
        'my_num' => $color_num,
        'fee' => $fee,
        'seller_get' => $seller_get,
        'np_total' => cm_본방냥총합(),
        'np_total_disp' => cm_냥표시(cm_본방냥총합()),
    ], $잔액응답()));
}

cm_json(['ok' => false, 'msg' => '알 수 없는 요청이에요.']);
