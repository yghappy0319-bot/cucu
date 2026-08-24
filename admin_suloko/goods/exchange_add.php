<?php
require_once $_SERVER['DOCUMENT_ROOT'].'/_common/config.php';
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_EXCHANGE.php";

// 매일 환율
$EXCHANGE_API_URL	=	"https://quotation-api-cdn.dunamu.com/v1/forex/recent?codes=FRX.KRWUSD";
$EXCHANGE_HEADER = array(
  "Content-Type: application/json"
);

$curl = curl_init();
curl_setopt($curl, CURLOPT_URL, $EXCHANGE_API_URL);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_HEADER, false);
curl_setopt($curl, CURLOPT_ENCODING, "");
curl_setopt($curl, CURLOPT_MAXREDIRS, 10);
curl_setopt($curl, CURLOPT_TIMEOUT, 30);
curl_setopt($curl, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
curl_setopt($curl, CURLOPT_HTTPHEADER, $EXCHANGE_HEADER);
// curl_setopt($curl, CURLOPT_POSTFIELDS, $INTERPARK_JS);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);
$return = json_decode($response, true);

$date = date("Y-m-d");
$info = $db->get_data("SELECT COUNT(*) AS cnt FROM EXCHANGE WHERE DATE='".$date."'");

if ($info['cnt'] > 0) {
  $sql = "UPDATE EXCHANGE SET WON = {$return[0]['basePrice']} WHERE DATE='".$date."'";
  $db->query($sql);
} else {
  $VAL = array();
  $VAL['EXCHANGE_NO'] = $db->get_data_one("SELECT MAX(EXCHANGE_NO) as EXCHANGE_NO FROM EXCHANGE") + 1;
  $VAL['WON'] = $return[0]['basePrice'];
  $VAL['DATE'] = $date;
  $VAL['mode'] = "insert";

  F_EXCHANGE($VAL);
}


// $sql1 = "update ExchangeRate set won = {$return[0]['basePrice']} , lastDate = now() where erIdx=1"; // 당첨금 환율
// $db->query($sql1);
// $sql2 = "update ExchangeRate set won = {$return[0]['ttBuyingPrice']} , lastDate = now() where erIdx=2"; // 티켓정산 환율
// $db->query($sql2);

/*
basePrice = 1163 // 기준환율
cashBuyingPrice = 1183.35 // 살때
cashSellingPrice = 1142.65 // 팔 때
ttBuyingPrice = 1151.7 // 받을때
ttSellingPrice = 1174.3 // 보낼때
// 당첨금 환율  : 기준환율 $return[0]['basePrice']
// 티켓정산 환율 : 받을떼 환율 $return[0]['ttBuyingPrice']
*/

alert_print("오늘날짜 환율 추가하였습니다.");
open_opener();
self_close();
?>
