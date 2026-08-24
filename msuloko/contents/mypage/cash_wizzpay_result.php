<?php
header("Content-Type:text/html; charset=utf-8;");

// 위즈페이 (wizzpay) 결제

$MID    = "sp1_cok05m";
$M_KEY  = "opd07keC6S2atzoRjBCWEcIsKYN87o/XTWqsA3D5+eJp5ilOtzIoAeV8o9acGOqfEb8Z6ULlYAvs4OlNpSsLeg==";
$apiUrl = "https://linkshop.wizzpay.co.kr/linkShop/wizzpay/api/payCard.do";

function setData($data) {
  $data["timestamp"] = (new DateTime())->format('YmdHis');
  $data["hash"] = md5($data["mid"] . $data["moid"] . $data["amt"] . $data["timestamp"]);

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

try {
  $data["mid"]            = $MID;  // MID 필수*
  $data["moid"]           = $_POST["ORDERID"]; // 가맹점 주문번호
  $data["goods_name"]     = "티켓 ".$_POST["ITEMNAME"]; // 상품명 필수*
  $data["amt"]            = $product['price']; // 결제금액 필수*
  $data["buyer_name"]     = str_han_cut($mem['NAME'], 30); // 구매자 이름 필수*
  $data["buyer_tel"]      = $mem['HP']; // 구매자 전화번호

  if (preg_match("/^[_\.0-9a-zA-Z-]+@([0-9a-zA-Z][0-9a-zA-Z-]+\.)+[a-zA-Z]{2,6}$/i", $mem['EMAIL']) == true) {
    $data["buyer_email"]  = $mem['EMAIL']; // 구매자 이메일
  } else {
    $data["buyer_email"]  = "";
  }

  $data["mall_user_id"]   = $M_login['user_id']; // 구매자 ID
  $data["user_ip"]        = $ip_address; // 회원사 고객 IP
  $data["card_num"]       = $_POST['BILLINFO']; // 카드번호 필수*
  $data["card_expire"]    = $_POST["EXPIREPERIOD"]; // 유효기간 yymm (2107) 필수*
  $data["card_pwd"]       = $_POST["CARDPWD"]; // 카드비번 ** 필수*
  $data["buyer_auth_num"] = $_POST["CARDAUTH"]; // 생년월일 yymmdd (200101) 필수*
  $data["card_quota"]     = $_POST["QUOTA"]; // 할부 필수*

  $postData["mid"]  = $data["mid"];
  $postData["data"] = setData($data);
  $postData = json_encode($postData);

  $headers = array("Content-Type: application/json; charset=UTF-8;", "Content-Length: ".strlen($postData)."",);

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

  // echo json_encode(json_decode(urldecode($response)), JSON_PRETTY_PRINT);
  $res = json_decode($response);

  if ($res->result_code == "3001") {
    // 결제 성공 시 작업 진행
    $CARDAUTHNO = $res->auth_code; // 카드승인번호
    $CARDCODE   = array_search($res->card_name, $card_op); // 카드사코드
    $CARDNAME   = $res->card_name; // 카드사 명
    $CARDNO     = substr_replace($data["card_num"], "******", 6, 6); // $res->cardNo; // 카드번호 (wayup은 카드번호를 리턴안함)
    $TID        = $res->tid; // 거래 키
    $QUOTA      = $data["card_quota"]; // 할부개월수
    $pg         = "wizzpay";
  } else {
    // 결제 실패 시 작업 진행
    // Debug용 로그 저장
    syslog(LOG_DEBUG, "[WIZZPAY - {$mem['USER_ID']}] REQ_DATA ".print_r($data, true));
    syslog(LOG_DEBUG, "[WIZZPAY - {$mem['USER_ID']}] RES_DATA ".print_r($response, true));

    // 실패 메세지 출력
    alert_print("[결제실패] ".$res->result_msg);
    meta_go("/contents/mypage/cash-charge.html");
    exit;
  }
} catch(Exception $e) {
  // 실패처리
  // Debug용 로그 저장
  syslog(LOG_DEBUG, "[WIZZPAY - {$mem['USER_ID']}] REQ_DATA ".print_r($data, true));
  syslog(LOG_DEBUG, "[WIZZPAY - {$mem['USER_ID']}] RES_DATA ".print_r($e, true));

  // 실패 메세지 출력
  alert_print("[결제실패] ".$e['result_msg']);
  meta_go("/contents/mypage/cash-charge.html");
  exit;
}
?>
