<?php
include_once('../common.php');
include_once('../lib/perfectpanel.php');

function 퍼팩트패널v2($url, $data, $api_key_v2){

  // cURL 초기화
  $ch = curl_init($url);

  // 옵션 설정
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Content-Type: application/json",
      "X-Api-Key: {$api_key_v2}"   // API Key 넣으세요
  ]);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

  // 실행
  $response = curl_exec($ch);

  // 오류 체크
  if (curl_errno($ch)) {
      echo "cURL Error: " . curl_error($ch);
  } else {
    $data = json_decode($response, true); // <- 두 번째 인자 true!
    return $data;

  }
  // 종료
  curl_close($ch);
}

if($status=="ok"){
  $처리시각 = date("ymdhis");
    $data = db_select("select * from bank_request where id = {$id} ");
    if($data['status']==3){
        die("status_ok");
    }

    $_mem = db_select("select * from member where mb_no = {$data['member_no']} ");

    $site = db_select("select * from site where idx = {$site_idx}");

    if($site['api_key_v2']){

      $url = $site['site']."/adminapi/v2/payments/add";
      $datas = [
          "username" => $data['user_id'],
          "amount" => $data['point'],
          "method" => $site['api_v2_method_type'] ? $site['api_v2_method_type'] : "Bonus",
          "memo" => "신용카드",
          "affiliate_commission" => true
      ];
      $result = 퍼팩트패널v2($url, $datas, $site['api_key_v2']);
      $payment = json_encode($result);         // 문자열
      $paymentArr = json_decode($payment, true);  // 배열로 복원
      if($paymentArr['data']['payment_id']){
        $sql = "update bank_request set status = 3, data = '수동 처리' where id = {$id} ";
        $result = db_query($sql);        
        die("balance_added");
      }else{
        die("balance_fail");
      }

    }else{

      $api = new Api();
      $_api_data = array(
          "{$site['site']}/adminapi/v1",
          "{$site['api_key']}"
      );


      $payment = $api->addPayment($_api_data, $data['user_id'], $data['point'], "무통장");
      //var_dump($payment);
      if ($payment->status == 'success') {
          $sql = "update bank_request set status = 3, data = '수동 처리' where id = {$id} ";
          $result = db_query($sql);

          //insert_point($_mem['mb_id'], "-".$site['mpoint'], "수동처리 {$site['mpoint']} 포인트 차감", "@smspoint", 0, $처리시각);

          die("balance_added");
      } else {
          die("balance_fail");
      }

    }



}

if($status=="del"){
  $sql = "update bank_request set del_status = 1 where id = {$id} ";
  $result = db_query($sql);
  echo $result;
}
