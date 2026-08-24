<?php
include_once __DIR__ . '/../common.php';

header('Content-Type: text/plain; charset=utf-8');

$midx_post = isset($_POST['midx']) ? (int) $_POST['midx'] : 0;
$idata_raw = isset($_POST['idata']) ? trim((string) $_POST['idata']) : '';

if (!$midx_post || $idata_raw === '' || !isset($_SESSION['midx']) || (int) $_SESSION['midx'] !== $midx_post) {
    die('로그인 정보가 올바르지 않습니다.');
}

/**
 * service2.html 계좌이체 금액과 동일 (부가세 없음, 할인 적용)
 *
 * @param array $row tb_pay_config 행
 */
function pay_service_cash_expected_bank_price(array $row)
{
    $price = isset($row['price']) ? (int) $row['price'] : 0;
    $discount = isset($row['discount']) ? (int) $row['discount'] : 0;
    if ($discount > 0) {
        $할인액 = $price * ($discount / 100);

        return (int) round($price - $할인액);
    }

    return $price;
}

// service2.html — tb_pay_config idx 기준 (상품명에 '/' 가 있어도 되도록 7토큰만 분리)
$idata_cfg = explode('/', $idata_raw, 7);
$from_pay_config = (count($idata_cfg) === 7 && isset($idata_cfg[5]) && $idata_cfg[5] === 'cash');

// service.htm 과 동일한 허용 상품 (금액·기간 변조 방지)
$ALLOW_AL = array('30-10000-100', '90-30000-330', '180-60000-660', '360-120000-1500');
$ALLOW_UN = array('30-50000', '90-142500', '180-270000', '360-480000');

$key = '';
$base_price = 0;
$type = '';
$pay_config_row = null;
$pay_config_pk = 0;

if ($from_pay_config) {
    $type = isset($idata_cfg[0]) ? $idata_cfg[0] : '';
    if (!in_array($type, array('알뜰형', '무제한'), true)) {
        die('캐시로 결제할 수 없는 상품입니다.');
    }
    $pay_config_pk = isset($idata_cfg[4]) ? (int) $idata_cfg[4] : 0;
    if ($pay_config_pk <= 0) {
        die('상품 정보가 올바르지 않습니다.');
    }
    $status_where = ($type === '무제한') ? ' and status = 1 ' : '';
    $pay_config_row = db_select('select * from tb_pay_config where idx = ' . $pay_config_pk . $status_where . ' limit 1 ');
    if (!$pay_config_row || !isset($pay_config_row['idx'])) {
        die('상품을 찾을 수 없습니다.');
    }
    if ((string) $pay_config_row['gubun'] !== $type) {
        die('상품 유형이 일치하지 않습니다.');
    }
    if ((int) $pay_config_row['service_day'] !== (int) $idata_cfg[1]) {
        die('서비스 기간 정보가 일치하지 않습니다.');
    }
    if ((int) $pay_config_row['service_sms'] !== (int) $idata_cfg[3]) {
        die('처리건수 정보가 일치하지 않습니다.');
    }
    $expected_cash = pay_service_cash_expected_bank_price($pay_config_row);
    if ($expected_cash <= 0 || (int) $idata_cfg[2] !== $expected_cash) {
        die('결제 금액이 올바르지 않습니다. 페이지를 새로고침 후 다시 시도해 주세요.');
    }
    $base_price = $expected_cash;
} else {
    $idata = explode('/', $idata_raw);
    $type = isset($idata[0]) ? $idata[0] : '';

    if (!in_array($type, array('알뜰형', '무제한', '포인트'), true)) {
        die('지원하지 않는 결제 유형입니다.');
    }

    if ($type === '무제한') {
        if (count($idata) !== 3) {
            die('잘못된 요청입니다.');
        }
        $key = $idata[1] . '-' . $idata[2];
        if (!in_array($key, $ALLOW_UN, true)) {
            die('선택한 상품 정보가 올바르지 않습니다.');
        }
        $base_price = (int) $idata[2];
    } else {
        if (count($idata) !== 4) {
            die('잘못된 요청입니다.');
        }
        $key = $idata[1] . '-' . $idata[2] . '-' . $idata[3];
        if (!in_array($key, $ALLOW_AL, true)) {
            die('선택한 상품 정보가 올바르지 않습니다.');
        }
        $base_price = (int) $idata[2];
    }
}

if ($base_price <= 0) {
    die('결제 금액을 확인할 수 없습니다.');
}

// 카드(PG)는 부가세 포함, 캐시 결제는 표시 금액(공급가)만 차감
$cash_cost = $base_price;

$extend_days = 0;
if ($from_pay_config) {
    $extend_days = (int) $idata_cfg[1];
} elseif (isset($idata)) {
    $extend_days = (int) $idata[1];
}

global $conn;

mysqli_begin_transaction($conn);

try {
    $회원 = db_select("select * from member where mb_no = {$midx_post} limit 1 for update");
    if (!$회원 || !isset($회원['mb_no'])) {
        throw new Exception('회원 정보를 찾을 수 없습니다.');
    }

    $cur_cash = isset($회원['mb_cash']) ? (int) $회원['mb_cash'] : 0;
    if ($cur_cash < $cash_cost) {
        throw new Exception(
            '보유 캐시가 부족합니다. (필요: ' . number_format($cash_cost) . '원, 보유: ' . number_format($cur_cash) . '원)'
        );
    }

    $deduct = db_query(
        'update member set mb_cash = mb_cash - ' . $cash_cost . ' where mb_no = ' . $midx_post
        . ' and COALESCE(mb_cash,0) >= ' . $cash_cost
    );
    if (!$deduct || mysqli_affected_rows($conn) !== 1) {
        throw new Exception('캐시 차감에 실패했습니다. 잔액을 확인해 주세요.');
    }

    if ($type === '알뜰형' || $type === '무제한') {
        $서비스상태 = 날짜비교($회원['mb_10']);
        $현재날자 = date('Y-m-d');
        if ($서비스상태 == 1) {
            $서비스시작일 = $현재날자;
            $서비스종료일 = date('Y-m-d', strtotime($서비스시작일 . ' +' . $extend_days . ' days'));
        } else {
            $서비스시작일 = $회원['mb_10'];
            $서비스종료일 = date('Y-m-d', strtotime($서비스시작일 . ' +' . $extend_days . ' days'));
        }

        if ($회원['mb_8'] == 2 && $type === '무제한') {
            db_query('update member set mb_point = 0 where mb_no = ' . $midx_post);
        }

        if ($회원['mb_8'] == 2 && $type === '알뜰형') {
            db_query('update member set mb_point = 0 where mb_no = ' . $midx_post);
        }

        $sql = 'update member set ';
        if ($type === '알뜰형') {
            $sql .= 'mb_point = mb_point + ' . $cash_cost . ' ';
        } else {
            $sql .= 'mb_point = 99999 ';
        }
        $sql .= ", service_start_date = '" . mysqli_real_escape_string($conn, $서비스시작일) . "' ";
        $sql .= ", mb_8 = '" . mysqli_real_escape_string($conn, $type) . "' ";
        $sql .= ", mb_10 = '" . mysqli_real_escape_string($conn, $서비스종료일) . "' ";
        $sql .= ", deldate = '' ";
        $sql .= 'where mb_no = ' . $midx_post;
        if (!db_query($sql)) {
            throw new Exception('서비스 연장 처리에 실패했습니다.');
        }

        회원사이트_서비스활성화($midx_post);

        $goods_label = '서비스 연장(캐시) - ' . number_format($cash_cost) . '원';
        $goods_esc = mysqli_real_escape_string($conn, $goods_label);
        $osql = 'insert into tb_orderlist set ';
        $osql .= 'midx = ' . $midx_post . ", ";
        $osql .= "subject = '{$goods_esc}', ";
        $osql .= 'amount = ' . $cash_cost . ', ';
        $osql .= 'status = 3001,';
        $osql .= 'regdate = now() ';
        if (!db_query($osql)) {
            throw new Exception('주문 기록에 실패했습니다.');
        }

        주문로그('캐시', $midx_post, "서비스_기간연장_{$서비스시작일}_{$서비스종료일}", $cash_cost, 1);

        $site = db_query("select * from site where member_no = {$midx_post} ");
        $내용 = '';
        $내용 .= $회원['mb_name'] . '(' . $회원['mb_id'] . ")\n";
        if ($site) {
            while ($st = mysqli_fetch_array($site)) {
                $내용 .= $st['site'] . "\n";
            }
        }
        $내용 .= '이용료(캐시) : ' . number_format($cash_cost) . '원 결제완료';
        다이랙트샌드('01022934444', '럭키뱅크 서비스 연장', $내용);
    } elseif ($type === '포인트' && !$from_pay_config) {
        $sql = 'update member set ';
        $sql .= 'mb_point = mb_point + ' . (int) $idata[2] . ' ';
        $sql .= ", mb_8 = '1' ";
        $sql .= 'where mb_no = ' . $midx_post;
        if (!db_query($sql)) {
            throw new Exception('포인트 충전 처리에 실패했습니다.');
        }

        주문로그('캐시', $midx_post, "포인트충전(캐시) {$idata[2]}", $cash_cost, 1);
    }

    $pk_val = ($pay_config_pk > 0) ? $pay_config_pk : 0;

    $userid_esc = mysqli_real_escape_string($conn, (string) $회원['mb_id']);
    $pv_esc = mysqli_real_escape_string($conn, 'cash_service/' . $idata_raw);
    $moid_esc = mysqli_real_escape_string($conn, 'sc_' . $midx_post . '_' . str_replace('.', '', uniqid('', true)));
    $gn_esc = mysqli_real_escape_string($conn, '이용료 캐시결제');

    $ins_log = 'insert into tb_pay_log set ';
    $ins_log .= 'pk_pay = ' . $pk_val . ', ';
    $ins_log .= 'midx = ' . $midx_post . ', ';
    $ins_log .= "userid = '{$userid_esc}', ";
    $ins_log .= 'amt = ' . $cash_cost . ", ";
    $ins_log .= "goodsname = '{$gn_esc}', ";
    $ins_log .= "pay_value = '{$pv_esc}', ";
    $ins_log .= "moid = '{$moid_esc}', ";
    $ins_log .= 'status = 1, ';
    $ins_log .= 'regdate = now() ';
    if (!db_query($ins_log)) {
        throw new Exception('결제 기록에 실패했습니다.');
    }

    mysqli_commit($conn);
} catch (Exception $e) {
    mysqli_rollback($conn);
    die($e->getMessage());
}

echo '1';
