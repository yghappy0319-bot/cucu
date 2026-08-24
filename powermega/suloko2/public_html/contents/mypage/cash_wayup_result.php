<?php
header("Content-Type:text/html; charset=utf-8;");

// Wayup에서 발급한 정보

$merchantID  = "suloko002m"; // 상점아이디
$merchantKey = 'OdR/KRgx9ZqjX2Xyx7X7qlud+Z1pP92AJahmvpTF6oqQUsy6ncHwDiUEhD5iUOl59VoxdElKuGBGON3QYddD8A=='; // 상점키
$apiUrl      = "https://api.wayup.co.kr/payment.keyin";

/*
****************************************************************************************
* <인증 결과 파라미터>
****************************************************************************************
*/
$payMethod   = "card"; // 결제수단
$mid         = $merchantID; // 상점 아이디
// $cpCd        = $_POST['CARDNAME']; // 카드사
$cardTypeCd  = "01"; // 카드구분 - 개인
$cardNo      = $_POST['BILLINFO']; // 카드번호
$expireYymm  = $_POST['EXPIREPERIOD']; // 유효기간
$ordAuthNo   = $_POST['CARDAUTH']; // 생년월일
$cardPw      = $_POST['CARDPWD']; // 비번 두자리
$quotaMon    = $_POST['QUOTA']; // 할부개월
$goodsNm     = $_POST['ITEMNAME']; // 상품명
$ordNo       = $_POST['ORDERID']; // 상점 주문번호
$goodsAmt    = $product['price']; // 결제 금액
$ordNm       = str_han_cut2($mem['NAME'], 20); // 구매자명
$ordTel      = $mem['HP']; // 구매자연락처
$notiUrl     = ""; // notiURL
$userIp      = ""; // $ip_address; // userIp
$trxCd       = 0; // 에스크로 여부(0:고정)
$mbsReserved = ""; // 상점 예약필드
$charSet     = "UTF-8";
$noIntFlg    = '0';
$pointFlg    = '0';

if (preg_match("/^[_\.0-9a-zA-Z-]+@([0-9a-zA-Z][0-9a-zA-Z-]+\.)+[a-zA-Z]{2,6}$/i", $mem['EMAIL']) == true) {
  $ordEmail  = $mem['EMAIL']; // 구매자이메일
} else {
  $ordEmail  = "";
}

/*
*******************************************************
* <해쉬암호화> (수정하지 마세요)
* SHA-256 해쉬암호화는 거래 위변조를 막기위한 방법입니다.
*******************************************************
*/
$ediDate = date("YmdHis");
$encData = bin2hex(hash('sha256', $mid.$ediDate.$goodsAmt.$merchantKey, true));
/*
****************************************************************************************
* <승인 결과 파라미터 정의>
* 샘플페이지에서는 승인 결과 파라미터 중 일부만 예시되어 있으며,
* 추가적으로 사용하실 파라미터는 연동메뉴얼을 참고하세요.
****************************************************************************************
*/

/*
****************************************************************************************
* <승인 요청 >
****************************************************************************************
*/
try{
  $data = Array(
    'payMethod'   => $payMethod,
    'mid'         => $mid,
    'ediDate'     => $ediDate,
    // 'cpCd'        => $cpCd,
    'cardTypeCd'  => $cardTypeCd,
    'cardNo'      => $cardNo,
    'expireYymm'  => $expireYymm,
    'ordAuthNo'   => $ordAuthNo,
    'cardPw'      => $cardPw,
    'quotaMon'    => $quotaMon,
    'goodsNm'     => $goodsNm,
    'ordNo'       => $ordNo,
    'goodsAmt'    => $goodsAmt,
    'ordNm'       => $ordNm,
    'ordTel'      => $ordTel,
    'ordEmail'    => $ordEmail,
    'notiUrl'     => $notiUrl,
    'userIp'      => $userIp,
    'trxCd'       => $trxCd,
    'mbsReserved' => $mbsReserved,
    'noIntFlg'    => $noIntFlg,
    'pointFlg'    => $pointFlg,
    'charSet'     => 'utf-8',
    'encData'     => $encData
  );
  $response = reqPost($data, $apiUrl);
  $res = json_decode($response);

  if ($res->resultCd == "3001") {
    // 결제 성공 시 작업 진행
    $CARDAUTHNO = $res->appNo; // 카드승인번호
    $CARDCODE   = $res->appCardCd; // 카드사코드
    $CARDNAME   = $card_op[$res->appCardCd];  // 카드사 명
    $CARDNO     = substr_replace($cardNo,"******",6,6); // $res->cardNo; // 카드번호 (wayup은 카드번호를 리턴안함)
    $TID        = $res->tid; // 거래 키
    $QUOTA      = $res->quota; //할부개월수
    $pg         = "wayup";
  } else {
    // 결제 실패 시 작업 진행
    // Debug용 로그 저장
    syslog(LOG_DEBUG, "[WAYUP - {$mem['USER_ID']}] REQ_DATA ".print_r($data, true));
    syslog(LOG_DEBUG, "[WAYUP - {$mem['USER_ID']}] RES_DATA ".print_r($response, true));

    // 실패 메세지 출력
    alert_print("[결제실패] ".$res->resultMsg);
    meta_go("/contents/mypage/cash-charge.html");
    exit;
  }
} catch(Exception $e) {
  // 실패처리
  // Debug용 로그 저장
  syslog(LOG_DEBUG, "[WAYUP - {$mem['USER_ID']}] REQ_DATA ".print_r($data, true));
  syslog(LOG_DEBUG, "[WAYUP - {$mem['USER_ID']}] RES_DATA ".print_r($e, true));

  // 실패 메세지 출력
  alert_print("[결제실패] ".$e['resultMsg']);
  meta_go("/contents/mypage/cash-charge.html");
  exit;
}
?>
