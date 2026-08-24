<?php
# error_reporting( E_ALL );
# ini_set( "display_errors", 1 );
header("Pragma: No-Cache");
include $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
include $_SERVER['DOCUMENT_ROOT']."/_library/function_T_CASH_LOG.php";
include $_SERVER['DOCUMENT_ROOT']."/_library/function_T_POINT_LOG.php";
include $_SERVER['DOCUMENT_ROOT']."/_common/pay_function.php";

include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly.php"; // 권한체크

$VAL = $_POST;

if ($VAL['IS_USE_N'] != 'C' && $VAL['IS_USE_N'] != 'P' && $VAL['IS_USE_N'] = '') {
  alert_print("처리유형이 잘못되었습니다.");
  history_go();
  exit;
}

if ($VAL['IS_USE_N'] == 'P' && $VAL['cancel_price'] <= 0) {
  alert_print("부분취소 금액이 누락되었습니다.");
  history_go();
  exit;
}

$info = $db->get_data("SELECT * FROM CASH WHERE CASH_NO='".$VAL['CASH_NO']."'");

if (empty($info['CASH_NO'])) {
  alert_print("결제건이 존재하지 않습니다.");
  history_go();
  exit;
}

$mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$info['USER_ID']."'");

if (empty($mem['MEMBER_NO'])) {
  alert_print("회원이 존재하지 않습니다.");
  history_go();
  exit;
}

$POINT = 0;

// 보유 캐시/포인트가 부족해도 카드 승인취소는 진행한다.
// 차감은 보유분까지만 하고, 나머지는 0으로 맞춘다.
$deductCash = 0;
$deductPoint = 0;

if ($VAL['IS_USE_N'] == "C" || ($VAL['IS_USE_N'] == "P" && $info['PRICE'] == $VAL['cancel_price'])) {
  $deductCash = min((int)$mem['CASH'], (int)$info['CASH']);
  if ($deductCash < 0) {
    $deductCash = 0;
  }

  if ($VAL['IS_USE_N'] == "C") {
    $지급포인트 = get_cash_config_by_cash($info['CASH']);
    $POINT = !empty($지급포인트['POINT']) ? (int)$지급포인트['POINT'] : 0;
    $deductPoint = min((int)$mem['POINT'], $POINT);
    if ($deductPoint < 0) {
      $deductPoint = 0;
    }
  }
} else {
  $CANCEL_PRICE  = preg_replace("/[^0-9]/", "", $VAL['cancel_price']);
  $CANCEL_CASH   = calc_cash_supply_by_price($CANCEL_PRICE);
  $deductCash = min((int)$mem['CASH'], (int)$CANCEL_CASH);
  if ($deductCash < 0) {
    $deductCash = 0;
  }
}

// _____________(S)결제 취소
$info['PG_ID'] == "wayup";
if ($info['PG_ID'] == "wayup") {
 //WAYUP
 require_once $_SERVER['DOCUMENT_ROOT']."/order/cancel_wayup_result.php";
} else if ($info['PG_ID'] == "wizzpay") {
 //WIZZPAY
 require_once $_SERVER['DOCUMENT_ROOT']."/order/cancel_wizzpay_result.php";
} else {
 //COAM
 //require_once $_SERVER['DOCUMENT_ROOT']."/order/cancel_coam_result.php";
}


// _____________(E)결제 취소

if ($VAL['IS_USE_N'] == "C" || ($VAL['IS_USE_N'] == "P" && $info['PRICE'] == $VAL['cancel_price'])) {
  if ($VAL['IS_USE_N'] == "C" && $deductPoint > 0) {
    $point_log = array();
    $point_log['mode'] = "insert";
    $point_log['COUPON_NO'] = 0;
    $point_log['ORDERS_NO'] = 0;
    $point_log['CASH_LOG_NO'] = $info['CASH_NO'];
    $point_log['POINT'] = $deductPoint;
    $point_log["N_POINT"] = $mem['POINT'] - $deductPoint;
    $point_log['O_POINT'] = $mem['POINT'];
    $point_log['USER_ID'] = $mem['USER_ID'];
    $point_log['MEMO'] = "캐시충전보너스 차감";
    $point_log['STATUS'] = "M";

    F_T_POINT_LOG($point_log);

    $query = "
      UPDATE
        MEMBER
      SET
        POINT = POINT - '{$deductPoint}'
      WHERE
        MEMBER_NO='{$mem['MEMBER_NO']}'
    ";
    $db->query($query);
  }

  if ($deductCash > 0) {
    $cash_log = array();
    $cash_log['mode']        = "insert";
    $cash_log['CASH_NO']     = $info['CASH_NO'];
    $cash_log['ORDERS_NO']   = 0;
    $cash_log['WINNING_NO']  = 0;
    $cash_log['INVOCE_NO']   = 0;
    $cash_log['CASH_LOG_NO'] = 0;
    $cash_log['PRICE']       = $info['PRICE'];
    $cash_log['CASH']        = $deductCash;
    $cash_log['N_CASH']      = $mem['CASH'] - $deductCash;
    $cash_log['O_CASH']      = $mem['CASH'];
    $cash_log['USER_ID']     = $mem['USER_ID'];
    $cash_log['MEMO']        = $S_login['user_id']."캐시구매취소";
    $cash_log['STATUS']      = "M";

    F_T_CASH_LOG($cash_log);

    $query  = "
      UPDATE
        MEMBER
      SET
        CASH = CASH - '{$deductCash}'
      WHERE
        MEMBER_NO = '{$info['MEMBER_NO']}'
      ";
    $db->query($query);
  }

  // 파트너 첫결제건 summary 처리
  if (!is_null($info["PARTNER_ID"])) {
    exec_partner_summary($info['PARTNER_ID'], "PAY_OUT");
  }

  $query = "
    UPDATE
      CASH
    SET
      STAFF_ID = '{$S_login['user_id']}',
      IS_USE = 'N',
      CANCAL_DATE = NOW(),
      PARTNER_ID = NULL
    WHERE
      CASH_NO = '{$VAL['CASH_NO']}'
    ";
  $db->query($query);

  #_______________첫결제
  // 취소건중 첫결제건인 경우
  if ($info['CASH_CNT'] == 1) {
    //다른 결제건이 있는지 확인하여 다른 결제건이 있을경우 제일 처음 결제건을 첫결제로 변경
    $CASH_NO = $db->get_data_one("SELECT CASH_NO FROM CASH WHERE MEMBER_NO = {$mem[MEMBER_NO]} AND IS_USE='Y' ORDER BY CASH_CNT ASC LIMIT 1");
    if ($CASH_NO) {
      $db->query("UPDATE CASH SET CASH_CNT = 1 WHERE CASH_NO = $CASH_NO");
    }
  }
} else {
  if($VAL['cancel_price']){
    $CANCEL_PRICE = preg_replace("/[^0-9]/", "", $VAL['cancel_price']); // 부분 취소 금액
  }else{
    $CANCEL_PRICE = 0;
  }
  $CANCEL_CASH  = calc_cash_supply_by_price($CANCEL_PRICE); // 부분 취소 캐시

  $cash_log = array();
  $cash_log['mode']        = "insert";
  $cash_log['CASH_NO']     = $info['CASH_NO'];
  $cash_log['ORDERS_NO']   = 0;
  $cash_log['WINNING_NO']  = 0;
  $cash_log['INVOCE_NO']   = 0;
  $cash_log['CASH_LOG_NO'] = 0;
  $cash_log['PRICE']       = $CANCEL_PRICE;
  $cash_log['CASH']        = $deductCash;
  $cash_log['N_CASH']      = $mem['CASH'] - $deductCash;
  $cash_log['O_CASH']      = $mem['CASH'];
  $cash_log['USER_ID']     = $mem['USER_ID'];
  $cash_log['MEMO']        = $S_login['user_id']."캐시구매부분취소";
  $cash_log['STATUS']      = "M";

  if ($deductCash > 0) {
    F_T_CASH_LOG($cash_log);

    $query  = "
      UPDATE
        MEMBER
      SET
        CASH = CASH - '{$deductCash}'
      WHERE
        MEMBER_NO = '{$info['MEMBER_NO']}'
      ";
    $db->query($query);
  }

  $query = "
    UPDATE
      CASH
    SET
      PRICE = PRICE - '{$CANCEL_PRICE}',
      CASH = CASH - '{$CANCEL_CASH}',
      IS_USE = 'N',
      STAFF_ID = '{$S_login['user_id']}'
    WHERE
      CASH_NO = '{$VAL['CASH_NO']}'
    ";
  $db->query($query);
  $RETURNMSG = "취소 성공하였습니다.";
}

#__________SMS 발송
$TEMPLET_NO   = 15;
$SMSTEMPLET   = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");

$from         = $fromHP;
$to           = $mem["HP"];
$CODESK       = "S";
$MEMBER_NO    = $mem['MEMBER_NO'];
$MEMBER_NAME  = $mem['NAME'];
$SUBJECT      = $SMSTEMPLET['SUBJECT'];
$SENDMSG      = $SMSTEMPLET['CONTENT'];
$SENDMSG      = str_replace('{USERID}', $mem['USER_ID'], $SENDMSG);
if ($VAL['IS_USE_N'] == "P") $SENDMSG = str_replace('취소', "부분취소", $SENDMSG);
$SENDMSG      = addslashes($SENDMSG);
$sms          = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG,"LMS");


$msg = $RETURNMSG; // "취소 성공하였습니다.";

alert_print($msg);
meta_go("/order/payment_list.html?{$VAL['PARAM']}");
?>
