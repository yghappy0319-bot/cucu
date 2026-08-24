<?php
include_once "../lib/function.php";
include_once "../_chk.php";

die(json_encode(array("status"=>0, "msg"=>"현재 시스템 점검으로 캐시 충전이 일시 중단되었습니다.")));

function getProductNameByPrice($price) {
    // 상품명과 가격을 키-값 쌍으로 저장
    $products = [
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

if(!$member['idx']){
  die(json_encode(array("status"=>0, "msg"=>"로그인 후 이용가능 합니다.")));
}

$cardnumber = $number1.$number2.$number3.$number4;
if (!is_numeric($cardnumber)) {
    die(json_encode(array("status"=>0, "msg"=>"카드번호는 숫자만 입력해주세요.")));
}

if($amount<0){
  die(json_encode(array("status"=>0, "msg"=>"충전하실 금액을 선택해주세요.")));
}

// 년도와 월을 추출
$year = substr($expiry, 0, 2); // 1, 2번째 자리 (년도)
$month = substr($expiry, 2, 2); // 3, 4번째 자리 (월)

// 년도와 월을 서로 바꾸기
$년도월 = $month . $year;
$상품명 = getProductNameByPrice($amount);
$_data = [
    "pay" => [
        "trxType" => "ONTR",
        "trackId" => $member['id'].date("his"),
        "amount" => $amount,
        "payerName" => $member['name'],
        "payerEmail" => "",
        "payerTel" => $member['phone'],
        "udf1" => "custom_value_1",
        "udf2" => "custom_value_2",
        "card" => [
            "number" => $cardnumber,
            "expiry" => $년도월,
            "cvv" => $cvv,
            "installment" => $installment
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
            "authPw" => $authPw,
            "authDob" => $authDob
        ]
    ]
];
$data = json_encode($_data);
$result = 카드($data);
$data11 = json_decode($result);
//var_dump($data11);

$sql = "insert into lr_card_log set ";
$sql.= "midx = {$member['idx']}, ";
$sql.= "userid = '{$member['id']}', ";
$sql.= "amount = {$amount}, "; // 결제금액
$sql.= "cash = {$cash},"; //충전캐시
$_추가포인트 = db_select("select * from lr_card_config where idx = {$cash_config_idx} ");

$sql.= "point = {$_추가포인트['add_point']},"; //메가파워월드포인트
$sql.= "pg_status1 = '{$data11->result->resultMsg}', ";
$sql.= "pg_status2 = '{$data11->result->advanceMsg}', ";
$sql.= "ch_status = '{$data11->result->resultCd}', ";
$sql.= "trxId = '{$data11->pay->trxId}', ";
$sql.= "trackId = '{$data11->pay->trackId}', ";
$sql.= "last4 = {$data11->pay->card->last4}, ";
$sql.= "issuer = '{$data11->pay->card->issuer}', ";
$sql.= "regdate = now() ";
$result = db_query($sql);

if($result){
  if($data11->result->resultCd=="0000"){
    //쿠폰,충전금누적액,충전처리
    카드결제_캐시지급($cash_config_idx, $amount, $cash, $member['idx']);
  }
}

$pay_result = array(
  "status"=> $data11->result->resultCd,
  "msg"=> $data11->result->advanceMsg
);

echo json_encode($pay_result);
