<?php
include_once "../lib/function.php";
exit;

function getProductNameByPrice($price) {
    // 상품명과 가격을 키-값 쌍으로 저장
    $products = [
        1004 => "D101",
        6600 => "D103",
        13200 => "커세어 시미터 RGB",
        19800 => "BL1 3487377",
        26400 => "BRK",
        33000 => "워커 클립온",
        66000 => "PVA",
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

function 카드($data){
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
    CURLOPT_POSTFIELDS =>$data,
    CURLOPT_HTTPHEADER => array(
      'Authorization: pk_d531-d349de-293-0d4dd',
      'Content-Type: application/json'
    ),
  ));

  $response = curl_exec($curl);
  curl_close($curl);
  return $response;
}

// 년도와 월을 추출
$year = substr($expiry, 0, 2); // 1, 2번째 자리 (년도)
$month = substr($expiry, 2, 2); // 3, 4번째 자리 (월)

// 년도와 월을 서로 바꾸기
$년도월 = $month . $year;
$amount = 1004;
$상품명 = getProductNameByPrice($amount);

$_data = [
    "pay" => [
        "trxType" => "ONTR",
        "trackId" => "dudrhks0319".date("his"),
        "amount" => $amount,
        "payerName" => "장영관",
        "payerEmail" => "",
        "payerTel" => "01022934444",
        "udf1" => "custom_value_1",
        "udf2" => "custom_value_2",
        "card" => [
            "number" => 5571620846673302,
            "expiry" => "3006",
            "cvv" => 574,
            "installment" => "00"
        ],
        "products" => [
            [
                "prodId" => "prod".date("his"),
                "name" => $상품명,
                "quantity" => 1,
                "price" => $amount
            ]
        ],
        "metadata" => [
            "cardAuth" => "true",
            "authPw" => "02",
            "authDob" => "870308"
        ]
    ]
];
// echo "<pre>";
// var_dump($_data);
// echo "</pre>";
$data = json_encode($_data);
$result = 카드($data);
//var_dump($data11);
$data11 = json_decode($result);
var_dump($result);


$pay_result = array(
    "status"=> $data11->result->resultCd,
    "msg"=> $data11->result->advanceMsg
  );

echo json_encode($pay_result);