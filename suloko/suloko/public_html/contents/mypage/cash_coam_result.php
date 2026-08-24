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
$DN_TIMEOUT = 30000; // max-time setting.

$ERC_NETWORK_ERROR = "-1";
$ERM_NETWORK = "Network Error";

/******************************************************
 * CPID    : 코엠에서 제공해 드린 CPID
 * CRYPTOKEY  : 코엠에서 제공해 드린 암복호화 PW(인증KEY - 64자의 Hash화 문자열)
 * IVKEY    : 고정값(변경불가)
******************************************************/
$CPID = "";
$CRYPTOKEY = ""; // mKey
$IVKEY = "";

// $TEST_AMOUNT="100";


/*[ 필수 데이터 ]***************************************/
$REQ_DATA = array();

/**************************************************
  * 결제 정보
**************************************************/
$REQ_DATA["AMOUNT"] = $product['price']; // 금액 $product['price']
$REQ_DATA["CURRENCY"] = "410";
$REQ_DATA["ITEMNAME"] = $_POST["ITEMNAME"]; // 상품명
$ITEMNAME = $_POST["ITEMNAME"]; // 상품명
$REQ_DATA["ORDERID"] = $_POST["ORDERID"]; // 주문번호

/**************************************************
  * 고객 정보
**************************************************/
$REQ_DATA["USERNAME"] = str_han_cut2($mem['NAME'], 10); // 회원명 (Max length : 10)

if (preg_match("/^[_\.0-9a-zA-Z-]+@([0-9a-zA-Z][0-9a-zA-Z-]+\.)+[a-zA-Z]{2,6}$/i", $mem['EMAIL']) == true) {
  $REQ_DATA["USEREMAIL"] = $mem['EMAIL']; // 회원이메일
} else {
  $REQ_DATA["USEREMAIL"] = "";
}

/**************************************************
  * 카드 정보
**************************************************/
$REQ_DATA["QUOTA"] = $_POST["QUOTA"]; // 할부개월수. 일시불:00, 2개월:02...
$REQ_DATA["BILLINFO"] = $_POST["BILLINFO"]; // 카드번호
$REQ_DATA["EXPIREPERIOD"] = $_POST["EXPIREPERIOD"]; // 유효기간 YYMM
$REQ_DATA["CARDPWD"] = $_POST["CARDPWD"]; // 비밀번호 앞 2자리
$REQ_DATA["CARDAUTH"] = $_POST["CARDAUTH"]; // 생년월일 YYMMDD

/**************************************************
  * 기본 정보
**************************************************/
$REQ_DATA["TXTYPE"] = "OTBILL";
$REQ_DATA["SERVICETYPE"] = "KEYIN";
$REQ_DATA["ITEMNAME"] = iconv("UTF-8", "EUC-KR", $REQ_DATA["ITEMNAME"]); // 상품명
$REQ_DATA["USERNAME"] = iconv("UTF-8", "EUC-KR", $REQ_DATA["USERNAME"]); // 회원명

$RES_DATA = CallCredit($REQ_DATA, false);

if ( $RES_DATA['RETURNCODE'] == "0000" ) {
  // 결제 성공 시 작업 진행
  // echo "1".urldecode( data2str( $RES_DATA ) );
  $CARDAUTHNO = $RES_DATA['CARDAUTHNO']; // 카드승인번호
  $CARDCODE   = $RES_DATA['CARDCODE']; // 카드사코드
  $CARDNAME   = $RES_DATA['CARDNAME']; // 카드사 명
  $CARDNO     = $RES_DATA['CARDNO']; // 카드번호
  $TID        = $RES_DATA['TID']; // 코엠 거래 키
  $QUOTA      = $RES_DATA["QUOTA"]; // 할부개월수
  $pg         = "coam";
} else {
  // 결제 실패 시 작업 진행
  // Debug용 로그 저장
  syslog(LOG_DEBUG, "[COAM - {$mem['USER_ID']}] REQ_DATA ".print_r($REQ_DATA, true));
  syslog(LOG_DEBUG, "[COAM - {$mem['USER_ID']}] RES_DATA ".print_r($RES_DATA, true));

  // 사용가능카드사오류(현대,삼성) 일때 웨이업 결제 시도
  if ( $RES_DATA['RETURNCODE'] == "2023" ) {
    // WAYUP keyin 결제
    require_once $_SERVER['DOCUMENT_ROOT']."/contents/mypage/cash_wayup_result.php";

  } else {
    // 실패 메세지 출력
    alert_print("[결제실패] ".$RES_DATA['RETURNMSG']);
    meta_go("/contents/mypage/cash-charge.html");
    exit;
  }
}
?>
