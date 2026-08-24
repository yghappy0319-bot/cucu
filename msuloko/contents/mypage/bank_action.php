<?php
/*
무통장 입금 처리
*/
$session_start_samesite = "Y";

require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_CASH.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_CASH_LOG.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_common/pay_function.php";

$RES = array("error"=>False, "msg"=>"");
$CHK_SECOND = "7"; // 중복입력 방지 INTERVAL (단위:초)

// 결제 이벤트 대상 확인
$cash_op = select_event_cash_op($M_login['user_id']);

if (!isset($cash_op[$_POST['cno']])) {
  $RES['msg'] = "상품을 선택해 주세요.";
  echo json_encode($RES);
  exit;
}

/* SLK-627 중복 신청 차단 */
$bank_cnt = $db->get_data("SELECT COUNT(*) AS CNT FROM CASH WHERE USER_ID = '{$M_login['user_id']}' AND CARDNAME = '무통장' AND IS_USE = 'N'"); //무통장 대기건 확인
if ($bank_cnt['CNT'] > 0) {
  $RES['msg']   = "진행중인 무통장 캐시충전 신청 건이 있습니다.<br>이전 신청 건을 입금 또는 취소 부탁드립니다.";
  $RES['goUrl'] = "/contents/mypage/cash-history.html";
  echo json_encode($RES);
  exit;
}
unset($bank_cnt);

$mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='".$M_login['user_id']."' LIMIT 1");

$product = $cash_op[$_POST['cno']];

if ($mem['USER_ID'] == '') {
  $RES['msg'] = "존재하지 않은 회원입니다.";
  echo json_encode($RES);
  exit;
}

$ITEMNAME = $_POST['ITEMNAME'];

// CASH 정보 입력
$VAL = array();

$patner['USER_ID'] = "";

if ($mem['PATNER_NO'] != '') {
  $patner = $db->get_data("SELECT * FROM PATNER WHERE PATNER_NO='".$mem['PATNER_NO']."'");

  if (!isset($patner['USER_ID'])) {
    $patner['USER_ID'] = "";
  }
}

$VAL['mode']         = "insert";
$VAL['MEMBER_NO']    = $mem['MEMBER_NO'];
$VAL['USER_ID']      = $mem['USER_ID'];
$VAL['IS_USE']       = "N";
$VAL['CASH']         = $product['cash'];
$VAL['PRICE']        = $product['price'];
$VAL['ITEMNAME']     = $ITEMNAME; // 상품명
$VAL['CARDNAME']     = "무통장";
$VAL['PATNER_ID']    = $patner['USER_ID'];
$VAL['SELLER_GUBUN'] = 'Mobile';
$VAL['CHK_SECOND']   = $CHK_SECOND;
$VAL['IN_IP']        = $ip_address;
$VAL['PARTNER_ID']   = ""; // 무통장 입금일 경우에는 입금 완료 시에만 처리(관리자)
$VAL['CASH_NO']      = $db->get_data_one("SELECT MAX(CASH_NO) as CASH_NO FROM CASH") + 1;
$VAL['IPOINT']       = $product['ipoint'];
$VAL['CASH_CNT']     = 0;
F_CASH($VAL); // 결제데이터 입력

// SMS발송
$TEMPLET_NO   = 21;
$SMSTEMPLET   = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");

$from         = $fromHP;
$to           = $mem["HP"];
$CODESK       = "S";
$MEMBER_NO    = $mem['MEMBER_NO'];
$MEMBER_NAME  = $mem['NAME'];
$SUBJECT      = $SMSTEMPLET['SUBJECT'];
$SENDMSG      = $SMSTEMPLET['CONTENT'];
$SENDMSG      = str_replace('{USERID}', $mem['USER_ID'], $SENDMSG);
$SENDMSG      = str_replace('{PRICE}', number_format($product['price']), $SENDMSG);
$SENDMSG      = addslashes($SENDMSG);
$sms          = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG,"LMS");

// 관리자 SMS발송
$TEMPLET_NO   = 22;
$SMSTEMPLET   = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");

$from         = $fromHP;
$to           = $SMSTEMPLET['SUBJECT'];
$CODESK       = "S";
$MEMBER_NO    = $mem['MEMBER_NO'];
$MEMBER_NAME  = $mem['NAME'];
$SUBJECT      = $SMSTEMPLET['SUBJECT'];
$SENDMSG      = $SMSTEMPLET['CONTENT'];
$SENDMSG      = str_replace('{USERID}', $mem['USER_ID'], $SENDMSG);
$SENDMSG      = str_replace('{USERNAME}', $mem['NAME'], $SENDMSG);
$SENDMSG      = str_replace('{PRICE}', number_format($product['price']), $SENDMSG);
$SENDMSG      = addslashes($SENDMSG);
$sms          = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG,"");


// if ($mem['EMAIL'] != '') {
//   $TEMPLET_NO = 2;

//   $EMAILTEMPLET = $db->get_data("SELECT * FROM EMAILTEMPLET WHERE TEMPLET_NO='".$TEMPLET_NO."'");
//   $to = $mem['EMAIL'];
//   $subject = $EMAILTEMPLET['SUBJECT'];
//   $content = $EMAILTEMPLET['CONTENT'];
//   $type = "2";

//   if ($EMAILTEMPLET['STOPYN'] == 'N') {
//     mailer($fname, $fmail, $to, $subject, $content, $type, $mem['NAME'], $mem['MEMBER_NO'], $TEMPLET_NO);
//   }
// }

$RES['error'] = true;
echo json_encode($RES);
?>
