<?php

/**
 * GHPay 카드결제 연동
 * - 다른 PG 결과 파일(coam/wayup/wizzpay)처럼
 *   결제만 수행하고, 공통 캐시/포인트 처리는 cash_action.php 에서 처리하도록 구성
 */

function getProductNameByPrice($price) {
  // 상품명과 가격을 키-값 쌍으로 저장
  $products = [
    1004   => "T101",
    6600   => "D103",
    13200  => "커세어 시미터 RGB",
    19800  => "BL1 3487377",
    26400  => "BRK",
    33000  => "워커 클립온",
    66000  => "PVA",
    132000 => "써지 100H",
    330000 => "캐디톡 MINIMI",
    660000 => "클린트 24Q"
  ];

  // 입력된 가격이 배열에 존재하면 상품명을 반환
  if (array_key_exists($price, $products)) {
    return $products[$price];
  } else {
    return "워커 클립온2";
  }
}

function ghpay_pay($data) {
  $curl = curl_init();
  curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://api.ghpayments.kr/api/pay',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $data,
    CURLOPT_HTTPHEADER => array(
      'Authorization: pk_d531-d349de-293-0d4dd',
      'Content-Type: application/json'
    ),
  ));

  $response = curl_exec($curl);
  curl_close($curl);
  return $response;
}

// cash_action.php 와 동일한 환경에서 호출된다고 가정
// $mem, $product, $_POST 값 등을 이용해 GHPay 결제를 수행

// 결제 금액 (VAT 포함 결제금액)
$amount = isset($product['price']) ? (int) $product['price'] : 0;

if ($amount <= 0) {
  alert_print("충전하실 금액을 선택해주세요.");
  meta_go("/contents/mypage/cash-charge.html");
  exit;
}

// 카드번호 (기존 PG와 동일하게 BILLINFO 사용)
$cardnumber = isset($_POST['BILLINFO']) ? preg_replace('/\D/', '', $_POST['BILLINFO']) : '';
if ($cardnumber === '' || !is_numeric($cardnumber)) {
  alert_print("카드번호는 숫자만 입력해주세요.");
  meta_go("/contents/mypage/cash-charge.html");
  exit;
}

// 유효기간 YYMM -> MMYY 로 변환
$expiry = isset($_POST['EXPIREPERIOD']) ? $_POST['EXPIREPERIOD'] : '';
if (strlen($expiry) !== 4) {
  alert_print("카드 유효기간을 정확히 입력해주세요.");
  meta_go("/contents/mypage/cash-charge.html");
  exit;
}
$month = substr($expiry, 0, 2); // 1, 2번째 자리 (년도)
$year = substr($expiry, 2, 2); // 3, 4번째 자리 (월)
$년도월 = $month.$year;

// 할부개월수
$installment = isset($_POST['QUOTA']) ? $_POST['QUOTA'] : "00";

// GHPay 에서 필요한 추가 인증값이 있는 경우 확장
$authPw = isset($_POST['CARDPWD']) ? $_POST['CARDPWD'] : "";
$authDob = isset($_POST['CARDAUTH']) ? $_POST['CARDAUTH'] : "";

// CVV 값이 별도 입력되는 경우 사용 (없다면 빈값 전달)
$cvv = isset($_POST['CVV']) ? $_POST['CVV'] : "";

$상품명 = (!empty($product['itemname']))
  ? ('캐시충전 '.$product['itemname'])
  : getProductNameByPrice($amount);

$_data = [
  "pay" => [
    "trxType"    => "ONTR",
    "trackId"    => $mem['USER_ID'].date("His"),
    "amount"     => $amount,
    "payerName"  => $mem['NAME'],
    "payerEmail" => "",
    "payerTel"   => $mem['HP'],
    "udf1"       => "custom_value_1",
    "udf2"       => "custom_value_2",
    "card" => [
      "number"      => $cardnumber,
      "expiry"      => $년도월,
      "cvv"         => $cvv,
      "installment" => $installment
    ],
    "products" => [
      [
        "prodId"   => "prod".date("His"),
        "name"     => $상품명,
        "quantity" => 1,
        "price"    => $amount
      ]
    ],
    "metadata" => [
      "cardAuth" => "true",
      "authPw"   => $authPw,
      "authDob"  => $authDob
    ]
  ]
];

$data = json_encode($_data, JSON_UNESCAPED_UNICODE);
$result = ghpay_pay($data);
$data11 = json_decode($result);

if (
  !$data11 ||
  !isset($data11->result->resultCd)
) {
  // 응답 파싱 실패
  syslog(LOG_DEBUG, "[GHPAY - {$mem['USER_ID']}] INVALID_RESPONSE ".print_r($result, true));
  alert_print("[결제실패] 결제 응답 처리 중 오류가 발생했습니다.");
  meta_go("/contents/mypage/cash-charge.html");
  exit;
}

// GHPay 결과코드 기준 (성공: 0000 으로 가정)
if ($data11->result->resultCd !== "0000") {
  // Debug용 로그 저장
  syslog(LOG_DEBUG, "[GHPAY - {$mem['USER_ID']}] REQ_DATA ".print_r($_data, true));
  syslog(LOG_DEBUG, "[GHPAY - {$mem['USER_ID']}] RES_DATA ".print_r($data11, true));

  $failMsg = isset($data11->result->advanceMsg) ? $data11->result->advanceMsg : "결제에 실패하였습니다.";
  alert_print("[결제실패] ".$failMsg);
  meta_go("/contents/mypage/cash-charge.html");
  exit;
}

// 결제 성공 시, 공통 후처리를 위해 cash_action.php 에서 사용하는 변수 세팅
$CARDAUTHNO = isset($data11->pay->trxId) ? $data11->pay->trxId : ""; // 카드승인번호 대용
$CARDCODE   = ""; // 카드사코드 정보를 별도로 제공하지 않는 경우 공백
$CARDNAME   = isset($data11->pay->card->issuer) ? $data11->pay->card->issuer : ""; // 카드사 명
$CARDNO     = isset($data11->pay->card->last4) ? "************".$data11->pay->card->last4 : ""; // 마스킹 카드번호
$TID        = isset($data11->pay->trxId) ? $data11->pay->trxId : ""; // 거래 키
$TRACKID    = isset($data11->pay->trackId) ? $data11->pay->trackId : ""; // 거래 키
$QUOTA      = $installment; // 할부개월수
$pg         = "ghpay";

?>

