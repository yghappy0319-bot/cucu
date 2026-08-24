<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_NON_WINING.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_CASH_LOG.php";

$VAL = $_POST;

$mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$M_login['user_id']."'");

if ($mem['USER_ID'] == '') {
 alert_print("존재하지 않은 회원입니다.");
 history_go();
 exit;
}

if ($mem['CASH'] < 20000) {
 alert_print("캐시가 부족합니다.");
 history_go();
 exit;
}

$order = $db->get_data("SELECT * FROM ORDERS WHERE ORDERS_NO='".$VAL['ORDERS_NO']."'");

if (!isset($order['ORDERS_NO'])) {
 alert_print("티켓이 존재하지 않습니다.");
 history_go();
 exit;
}

if ($order['NON_MAIL'] == 'Y') {
 alert_print("이미 배송신청한 티켓입니다.");
 history_go();
 exit;
}

// 미국 요청
$postData = json_encode([
  clientId => $usClientId,
  orderNo  => $VAL['ORDERS_NO'],
  name     => $VAL['NAME'],
  contact  => $VAL['HP'],
  zipcode  => $VAL['POST1'],
  addr1    => $VAL['ADDR1'],
  addr2    => $VAL['ADDR2']
]);
$path = "delivery/request";
$result = json_decode(sendRequestToUSAServer($path, $postData), true);

// 미국에 티켓 정보가 없는 경우가 있어서 오류 처리 하지 않음
// if ($result["code"] != "0000") {
//   alert_print("배송 신청에 실패했습니다.");
//   history_go();
//   exit;
// }

$VAL['mode'] = "insert";
$VAL['WINNING_NO'] = $db->get_data_one("SELECT MAX(WINNING_NO) as WINNING_NO FROM NON_WINING") + 1;

F_NON_WINING($VAL);

$sql = "UPDATE ORDERS SET NON_MAIL = 'Y' WHERE ORDERS_NO = '".$VAL['ORDERS_NO']."'";
$db->query($sql);

$cash_price = 20000;

// 캐시사용로그
$cash_log = array();
$cash_log['mode']        = "insert";
$cash_log['CASH_NO']     = 0;
$cash_log['ORDERS_NO']   = 0;
$cash_log['WINNING_NO']  = $VAL['WINNING_NO'];
$cash_log['INVOCE_NO']   = 0;
$cash_log['CASH_LOG_NO'] = 0;
$cash_log['PRICE']       = 0;
$cash_log['CASH']        = $cash_price;
$cash_log['N_CASH']      = $mem['CASH'] - $cash_price;
$cash_log['O_CASH']      = $mem['CASH'];
$cash_log['USER_ID']     = $mem['USER_ID'];
$cash_log['MEMO']        = "낙첨복권배송비";
$cash_log['STATUS']      = "M";
F_T_CASH_LOG($cash_log);

$sql = "UPDATE MEMBER SET CASH=CASH-".$cash_price." WHERE MEMBER_NO = '".$mem['MEMBER_NO']."'";
$db->query($sql);

$TEMPLET_NO = 16;
$SMSTEMPLET = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");

$from        = $fromHP;
$to          = $VAL["HP"];
$CODESK      = "S";
$MEMBER_NO   = $mem['MEMBER_NO'];
$MEMBER_NAME = $mem['NAME'];
$auth_num    = str_rand(6,"0123456789");
$SUBJECT     = $SMSTEMPLET['SUBJECT'];
$SENDMSG     = $SMSTEMPLET['CONTENT'];
$SENDMSG     = str_replace('{USERID}', $mem['USER_ID'], $SENDMSG);
$SENDMSG     = addslashes($SENDMSG);

$sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG, "LMS");

alert_print("접수되었습니다.");
meta_go("/contents/mypage/non-winning.html");
?>
