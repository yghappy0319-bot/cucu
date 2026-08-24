<?php
/**_________________________________________________
 * 환율 업데이트
 * - 매시 1분 마다 (ex: 9시 1분, 10시 1분)
 * _________________________________________________
 */
require_once $_SERVER['DOCUMENT_ROOT'].'/_common/config.php';
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_EXCHANGE.php";

$db->query("insert into CRON_LOG set FILE = 'exchange', REG_DATE = now() ");


$EXCHANGE_API_URL = "https://quotation-api-cdn.dunamu.com/v1/forex/recent?codes=FRX.KRWUSD";
$EXCHANGE_HEADER  = ["Content-Type: application/json"];
$nTime            = time();
$hour             = date("H", $nTime);
$date             = date("Y-m-d");

syslog(LOG_DEBUG, "========== Exchange Rate Start =========>");
syslog(LOG_DEBUG, "");

syslog(LOG_DEBUG, "----------------------");
syslog(LOG_DEBUG, "date: {$date}");
syslog(LOG_DEBUG, "hour: {$hour}");
syslog(LOG_DEBUG, "----------------------");
syslog(LOG_DEBUG, "");

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

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);
$return = json_decode($response, true);

$date = date("Y-m-d");
$info = $db->get_data("SELECT COUNT(*) AS cnt FROM EXCHANGE WHERE DATE='{$date}'");

if ($info['cnt'] > 0) {
  $sql = "UPDATE EXCHANGE SET WON = {$return[0]['basePrice']} WHERE DATE='{$date}'";
  $db->query($sql);
} else {
  $VAL = array();
  $VAL['EXCHANGE_NO'] = $db->get_data_one("SELECT MAX(EXCHANGE_NO) as EXCHANGE_NO FROM EXCHANGE") + 1;

  $VAL['WON']  = $return[0]['basePrice'];
  $VAL['DATE'] = $date;
  $VAL['mode'] = "insert";

  F_EXCHANGE($VAL);
}

syslog(LOG_DEBUG, "========== Exchange Rate End =========>");
syslog(LOG_DEBUG, "");
