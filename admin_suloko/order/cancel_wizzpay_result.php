<?php
header("Content-Type:text/html; charset=utf-8;");

// 위즈페이 (wizzpay) 결제 - 취소

// MID 변경으로 인한 분기
//if ($info["CON_REG_DATE"] > "2025-08-19 09:20:00") {
//
//} else if ($info["CON_REG_DATE"] <= "2025-08-19 09:20:00" && $info["CON_REG_DATE"] > "2025-05-29 10:30:00") {
//  $MID = "sp1_won05m";
//} else if ($info["CON_REG_DATE"] <= "2025-05-29 10:30:00" && $info["CON_REG_DATE"] > "2024-12-16 14:25:00") {
//  $MID = "sp1_fan05m";
//} else if ($info["CON_REG_DATE"] <= "2024-12-16 14:25:00" && $info["CON_REG_DATE"] > "2024-07-05 11:40:00") {
//  $MID = "sp1_gssukm";
//} else {
//  $MID = "sp1_tmsukm";
//}

$MID = "sp1_cok05m";

$M_KEY = "opd07keC6S2atzoRjBCWEcIsKYN87o/XTWqsA3D5+eJp5ilOtzIoAeV8o9acGOqfEb8Z6ULlYAvs4OlNpSsLeg==";
$apiUrl = "https://linkshop.wizzpay.co.kr/linkShop/wizzpay/api/payCancel.do";

function setData($data) {
  $data["timestamp"] = (new DateTime())->format('YmdHis');
  $data["hash"] = md5($data["mid"] . $data["tid"] . $data["cancel_amt"] . $data["timestamp"]);
  $encrypt = encrypt(json_encode($data, JSON_UNESCAPED_UNICODE));
  return $encrypt;
}

function encrypt($data) {
  $keyset = createKey();
  return openssl_encrypt($data, 'aes-256-cbc', $keyset[0], 0, $keyset[1]);
}

function decrypt($data) {
  $keyset = createKey();
  return openssl_decrypt($data, 'aes-256-cbc', $keyset[0], 0, $keyset[1]);
}

function createKey() {
  global $M_KEY;

  if (strlen($M_KEY) > 32) {
    $M_KEY = substr($M_KEY, 0, 32);
  }
  $iv = chr(0).chr(0).chr(0).chr(0).chr(0).chr(0).chr(0).chr(0).chr(0).chr(0).chr(0).chr(0).chr(0).chr(0).chr(0).chr(0);
  return [$M_KEY, $iv];
}

$tid        = $info['TID']; // 거래 ID
$ordNo      = ''; // 주문 번호
$canAmt     = ($VAL['IS_USE_N'] == "P") ? $VAL['cancel_price'] : $info['PRICE']; // 취소 금액
$partCanFlg = ($VAL['IS_USE_N'] == "P") ? 1 : 0; // 부분취소여부 0:전체취소, 1:부분취소
$notiUrl    = ''; // NOTI URL
$canMsg     = '고객요청'; // 취소 사유
$cancel_pwd = "12"; // 취소 비밀번호

try {
  $data["mid"]                 = $MID;
  $data["cancel_amt"]          = $canAmt; // 취소금액 필수*
  $data["tid"]                 = $tid; // TID 필수*
  $data["partial_cancel_code"] = $partCanFlg; // 취소타입 0 전체취소, 1 부분취소 필수*
  $data["cancel_name"]         = ""; // 취소 요청자
  $data["cancel_msg"]          = $canMsg; // 취소 사유
  $data["cancel_pwd"]          = $cancel_pwd; // 취소 비밀번호 필수*
  $data["cancel_ip"]           = $ip_address; // 취소 가맹점 IP
  $data["cancel_id"]           = substr($info['USER_ID'], 0, 10); // 취소 유저 ID, Max 10

  $postData["mid"]  = $data["mid"];
  $postData["data"] = setData($data);
  $postData = json_encode($postData);

  $headers = array( "Content-Type: application/json; charset=UTF-8;", "Content-Length: ".strlen($postData)."", );

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $apiUrl);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $response = curl_exec($ch);

  curl_close($ch);

  //echo json_encode(json_decode(urldecode($response)), JSON_PRETTY_PRINT);;
  $res = json_decode($response);

  if ($res->result_code == "2001") {
    // 취소 성공 시 작업 진행
    syslog(LOG_DEBUG, "[WIZZPAY - {$mem['USER_ID']}] RES_DATA ".print_r($response, true));

    $RETURNMSG = $res->result_msg;
  } else {
    // 취소 실패 시 작업 진행
    // Debug용 로그 저장
    syslog(LOG_DEBUG, "[WIZZPAY - {$mem['USER_ID']}] REQ_DATA ".print_r($data, true));
    syslog(LOG_DEBUG, "[WIZZPAY - {$mem['USER_ID']}] RES_DATA ".print_r($response, true));

    // 실패 메세지 출력
    alert_print("[취소실패] ".$res->result_msg);
    meta_go("/order/payment_list.html?{$VAL['PARAM']}");
    exit;
  }
} catch(Exception $e) {
  // 실패처리
  $e->getMessage();
  $ResultCode = "9999";
  $ResultMsg = "통신실패";

  // Debug용 로그 저장
  syslog(LOG_DEBUG, "[WIZZPAY - {$mem['USER_ID']}] REQ_DATA ".print_r($data, true));
  syslog(LOG_DEBUG, "[WIZZPAY - {$mem['USER_ID']}] RES_DATA ".print_r($e, true));

  // 실패 메세지 출력
  alert_print("[취소실패] ".$ResultMsg);
  meta_go("/order/payment_list.html?{$VAL['PARAM']}");
  exit;
}
?>
