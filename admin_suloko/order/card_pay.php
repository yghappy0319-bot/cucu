<?php
header("Pragma: No-Cache");
include $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
include $_SERVER['DOCUMENT_ROOT']."/_library/function_T_CASH_LOG.php";
include $_SERVER['DOCUMENT_ROOT']."/_library/function_T_POINT_LOG.php";
include $_SERVER['DOCUMENT_ROOT']."/_common/pay_function.php";

include $_SERVER["DOCUMENT_ROOT"]."/_common/_check-auth-readonly.php";

function getCardCashGrantedNet($cashNo) {
  global $db;
  return (int)$db->get_data_one("
    SELECT COALESCE(SUM(CASE WHEN STATUS='P' THEN CASH WHEN STATUS='M' THEN -CASH ELSE 0 END), 0)
    FROM T_CASH_LOG
    WHERE CASH_NO='{$cashNo}'
      AND (
        MEMO = '캐시구매충전(카드)'
        OR MEMO LIKE '%캐시구매취소%'
        OR MEMO LIKE '%캐시구매부분취소%'
      )
  ");
}

function getBonusPointGrantedNet($cashNo) {
  global $db;
  return (int)$db->get_data_one("
    SELECT COALESCE(SUM(CASE WHEN STATUS='P' THEN POINT WHEN STATUS='M' THEN -POINT ELSE 0 END), 0)
    FROM T_POINT_LOG
    WHERE CASH_LOG_NO='{$cashNo}'
      AND MEMO='캐시충전보너스'
  ");
}

$VAL = $_POST;

if ($VAL['CASH_NO'] == '' || $VAL['CASH_NO'] <= 0) {
  alert_print("결제번호오류");
  meta_go("/order/payment_list.html?".$PARAM);
  exit;
}

if ($VAL['PROCESS_TYPE'] != 'S' && $VAL['PROCESS_TYPE'] != 'C' && $VAL['PROCESS_TYPE'] != 'D') {
  alert_print("처리유형이 잘못되었습니다.");
  meta_go("/order/payment_list.html?".$PARAM);
  exit;
}

$info = $db->get_data("SELECT * FROM CASH WHERE CASH_NO='{$VAL['CASH_NO']}'");

if (empty($info['CASH_NO'])) {
  alert_print("결제건이 존재하지 않습니다.");
  meta_go("/order/payment_list.html?".$PARAM);
  exit;
}

$cashGrantedNet = getCardCashGrantedNet($VAL['CASH_NO']);

if ($VAL['PROCESS_TYPE'] == 'D') {
  if ($info['IS_USE'] == 'D') {
    alert_print("이미 삭제된 건입니다.");
    meta_go("/order/payment_list.html?".$PARAM);
    exit;
  }

  $db->query("UPDATE CASH SET IS_USE='D', STAFF_ID='{$S_login['user_id']}', CANCAL_DATE = NOW() WHERE CASH_NO = '{$VAL['CASH_NO']}'");

  $msg = "결제요청건이 삭제되었습니다.";
  alert_print($msg);
  meta_go("/order/payment_list.html?".($VAL['PARAM'] ? $VAL['PARAM'] : $PARAM));
  exit;
}

$mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='{$info['USER_ID']}'");

if (empty($mem['USER_ID'])) {
  alert_print("회원정보가 존재하지 않습니다.");
  meta_go("/order/payment_list.html?".$PARAM);
  exit;
}

$alreadyApproved = ($info['IS_USE'] == 'Y');

if ($VAL['PROCESS_TYPE'] == 'S') {
  if (!$alreadyApproved) {
    $CASH_CNT = $db->get_data_one("SELECT COUNT(*) AS CNT FROM CASH WHERE MEMBER_NO = {$mem['MEMBER_NO']} AND IS_USE='Y'") + 1;
    $query = "UPDATE CASH SET IS_USE = 'Y', CON_REG_DATE = NOW(), STAFF_ID='{$S_login['user_id']}', CASH_CNT = {$CASH_CNT}";
    $query .= " WHERE CASH_NO = '{$VAL['CASH_NO']}'";
    $db->query($query);
  } else {
    $db->query("UPDATE CASH SET CON_REG_DATE = NOW(), STAFF_ID='{$S_login['user_id']}' WHERE CASH_NO = '{$VAL['CASH_NO']}'");
  }

  $msg = "승인완료 상태로 처리되었습니다.";
  alert_print($msg);
  meta_go("/order/payment_list.html?".($VAL['PARAM'] ? $VAL['PARAM'] : $PARAM));
  exit;
}

if ($cashGrantedNet >= (int)$info['CASH']) {
  alert_print("이미 캐시가 지급된 건입니다.");
  meta_go("/order/payment_list.html?".$PARAM);
  exit;
}

$bonusPointGrantedNet = getBonusPointGrantedNet($VAL['CASH_NO']);
$포인트쿼리 = "select * from CASH_CONFIG where CASH = {$info['CASH']} ";
$지급포인트 = $db->get_data($포인트쿼리);
$grantPoint = (!empty($지급포인트['POINT']) && (int)$지급포인트['POINT'] > 0) ? (int)$지급포인트['POINT'] : 0;

if (!$alreadyApproved) {
  $CASH_CNT = $db->get_data_one("SELECT COUNT(*) AS CNT FROM CASH WHERE MEMBER_NO = {$mem['MEMBER_NO']} AND IS_USE='Y'") + 1;
  $query = "UPDATE CASH SET IS_USE = 'Y', CON_REG_DATE = NOW(), STAFF_ID='{$S_login['user_id']}', CASH_CNT = {$CASH_CNT}";
  $query .= " WHERE CASH_NO = '{$VAL['CASH_NO']}'";
  $db->query($query);
} else {
  $db->query("UPDATE CASH SET CON_REG_DATE = NOW(), STAFF_ID='{$S_login['user_id']}' WHERE CASH_NO = '{$VAL['CASH_NO']}'");
}

# 파트너 첫결제
$RESULT_PARTNER_ID = 0;
if ($mem['PARTNER_ID']) {
  $RESULT_PARTNER_ID = exec_partner_firstsale($mem['USER_ID'], $mem['PARTNER_ID'], $mem['REG_DATE']);
}

# 파트너 summary 처리
if ($RESULT_PARTNER_ID) {
  exec_partner_summary($mem['PARTNER_ID'], "PAY");
  $db->query("UPDATE CASH SET PARTNER_ID = '{$RESULT_PARTNER_ID}' WHERE CASH_NO = '{$VAL['CASH_NO']}'");
}

$remainCash = (int)$info['CASH'] - $cashGrantedNet;
$remainPoint = max(0, $grantPoint - $bonusPointGrantedNet);

## MEMBER UPDATE
$query = "UPDATE MEMBER SET CASH = CASH + ".$remainCash;
if ($remainPoint > 0) {
  $query .= ", POINT = POINT + {$remainPoint} ";
}
$query .= " WHERE USER_ID = '".$info['USER_ID']."' ";
$db->query($query);

## T_CASH_LOG INSERT
$cash_log = array();
$cash_log['mode']        = "insert";
$cash_log['CASH_NO']     = $VAL['CASH_NO'];
$cash_log['ORDERS_NO']   = 0;
$cash_log['WINNING_NO']  = 0;
$cash_log['INVOCE_NO']   = 0;
$cash_log['CASH_LOG_NO'] = 0;
$cash_log['PRICE']       = $info['PRICE'];
$cash_log['CASH']        = $remainCash;
$cash_log['N_CASH']      = $mem['CASH'] + $remainCash;
$cash_log['O_CASH']      = $mem['CASH'];
$cash_log['USER_ID']     = $mem['USER_ID'];
$cash_log['MEMO']        = "캐시구매충전(카드)";
$cash_log['STATUS']      = "P";
F_T_CASH_LOG($cash_log);

if ($remainPoint > 0) {
  $point_log = array();
  $point_log["mode"] = "insert";
  $point_log["COUPON_NO"] = 0;
  $point_log["ORDERS_NO"] = 0;
  $point_log["CASH_LOG_NO"] = $VAL['CASH_NO'];
  $point_log["POINT"] = $remainPoint;
  $point_log["N_POINT"] = $mem['POINT'] + $remainPoint;
  $point_log["O_POINT"] = $mem['POINT'];
  $point_log["USER_ID"] = $mem['USER_ID'];
  $point_log["MEMO"] = "캐시충전보너스";
  $point_log["STATUS"] = "P";
  F_T_POINT_LOG($point_log);
}

## SMS
$TEMPLET_NO   = 24;
$SMSTEMPLET   = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");

$from         = $fromHP;
$to           = $mem["HP"];
$CODESK       = "S";
$MEMBER_NO    = $mem['MEMBER_NO'];
$MEMBER_NAME  = $mem['NAME'];
$SUBJECT      = $SMSTEMPLET['SUBJECT'];
$SENDMSG      = $SMSTEMPLET['CONTENT'];
$SENDMSG      = str_replace('{USERID}', $mem['USER_ID'], $SENDMSG);
$SENDMSG      = str_replace('{PRICE}', number_format($info['CASH']), $SENDMSG);
$SENDMSG      = addslashes($SENDMSG);
$sms          = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG,"LMS");

$msg = "승인완료 및 캐시 지급 처리가 완료되었습니다.";
alert_print($msg);
meta_go("/order/payment_list.html?".($VAL['PARAM'] ? $VAL['PARAM'] : $PARAM));
