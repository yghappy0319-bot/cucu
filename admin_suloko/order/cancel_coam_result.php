<?php

/*****************************************************
 * 연동에 필요한 Function 및 변수값 설정
 *
 * 연동에 대한 문의사항 있으시면 기술지원팀으로 연락 주십시오.
 * EMail : dev@coam.co.kr
******************************************************/

/******************************************************
 *  DN_CREDIT_URL  : 결제 서버 정의
******************************************************/
$DN_CREDIT_URL = "https://pgweb.coam.co.kr/keyin/";

/******************************************************
 *  Set Timeout
******************************************************/
$DN_CONNECT_TIMEOUT = 5000;
$DN_TIMEOUT = 30000; //max-time setting.

$ERC_NETWORK_ERROR = "-1";
$ERM_NETWORK = "Network Error";

/******************************************************
 * CPID    : 코엠에서 제공해 드린 CPID
 * CRYPTOKEY  : 코엠에서 제공해 드린 암복호화 PW(인증KEY - 64자의 Hash화 문자열)
 * IVKEY    : 고정값(변경불가)
******************************************************/
$CPID = "";
$CRYPTOKEY = "";//mKey
$IVKEY = "";

//$TEST_AMOUNT="100";

//***** 카드 OTBILL수기결제 취소 *****

/*[ 필수 데이터 ]***************************************/
$REQ_DATA = array();

/**************************************************
 * CP 정보
**************************************************/
//$REQ_DATA["CPID"] = $CPID;

/**************************************************
 * 결제 정보
**************************************************/
$REQ_DATA["TID"] = $info['TID']; //결제 완료 TID

/**************************************************
 * 취소 정보
**************************************************/
if ($VAL['IS_USE_N'] == "P") {
  $REQ_DATA["AMOUNT"] = $VAL['cancel_price'];
} else {
  $REQ_DATA["AMOUNT"] = $info['PRICE'];  //취소금액
}
$REQ_DATA["CANCELTYPE"] = $VAL['IS_USE_N']; //C:전체취소, P:부분취소, 미송신시 ‘C’로판단
$REQ_DATA["CANCELDESC"] = "Item not delivered"; //취소사유

/**************************************************
 * 기본 정보
 **************************************************/
$REQ_DATA["TXTYPE"] = "CANCEL";
$REQ_DATA["SERVICETYPE"] = "KEYIN";

//취소 요청
$RES_DATA = CallCredit($REQ_DATA, false);

if ( $RES_DATA['RETURNCODE'] == "0000" ) {
  // 결제 성공 시 작업 진행
  syslog(LOG_DEBUG, "[COAM - {$mem['USER_ID']}] RES_DATA ".print_r($RES_DATA,true));

  $RETURNMSG = $RES_DATA['RETURNMSG'];

} else {
  // 결제 실패 시 작업 진행
  // Debug용 로그 저장
  syslog(LOG_DEBUG, "[COAM - {$mem['USER_ID']}] REQ_DATA ".print_r($REQ_DATA,true));
  syslog(LOG_DEBUG, "[COAM - {$mem['USER_ID']}] RES_DATA ".print_r($RES_DATA,true));

  // 실패 메세지 출력
  alert_print("[취소실패] ".$RES_DATA['RETURNMSG']);
  meta_go("/order/payment_list.html?{$VAL['PARAM']}");
  exit;
}

?>
