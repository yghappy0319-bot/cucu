<?php
/**_________________________________________________
 * 환율 업데이트
 * - 매시 1분 마다 (ex: 9시 1분, 10시 1분)
 * _________________________________________________
 */
require_once $_SERVER['DOCUMENT_ROOT'].'/_common/config.php';
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_EXCHANGE.php";

// 조회일
$now  = new DateTime();
$date = $now->format("Y-m-d");
$hour = $now->format('H');

syslog(LOG_DEBUG, "========== Exchange Rate Start =========>");
syslog(LOG_DEBUG, "");

syslog(LOG_DEBUG, "----------------------");
syslog(LOG_DEBUG, "date: {$date}");
syslog(LOG_DEBUG, "hour: {$hour}");
syslog(LOG_DEBUG, "----------------------");
syslog(LOG_DEBUG, "");

$data = fetchExchangeRate();

if (!$data || !isset($data[1]["rate"])) {
  syslog(LOG_DEBUG, '환율 API 업데이트 실패 -> API 응답 데이터 오류');
  exit;
}

$rate = round($data[1]["rate"]);

$info = $db->get_data("SELECT COUNT(*) AS cnt FROM EXCHANGE WHERE DATE='{$date}'");

if ($info['cnt'] > 0) {
  $db->query("UPDATE EXCHANGE SET WON = {$rate} WHERE DATE='{$date}'");
  syslog(LOG_DEBUG, "환율({$rate}) 업데이트 완료");
} else {
  $VAL = array();
  $VAL['EXCHANGE_NO'] = $db->get_data_one("SELECT MAX(EXCHANGE_NO) AS EXCHANGE_NO FROM EXCHANGE") + 1;
  $VAL['WON']  = $rate;
  $VAL['DATE'] = $date;
  $VAL['mode'] = "insert";
  F_EXCHANGE($VAL);
  syslog(LOG_DEBUG, "환율({$rate}) 저장 완료");
}
syslog(LOG_DEBUG, "========== Exchange Rate End =========>");
syslog(LOG_DEBUG, "");

function fetchExchangeRate() {
  $url = "http://api.manana.kr/exchange/rate.json";

  $options = [
    "http" => [
      "method" => "GET",
      "header" => "Content-Type: application/json\r\n"
    ]
  ];
  $context  = stream_context_create($options);
  $response = file_get_contents($url, false, $context);

  if ($response === FALSE) {
    return false;
  }
  return json_decode($response, true);
}
