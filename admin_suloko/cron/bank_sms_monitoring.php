<?
#################################################
## 일정 시간 동안 무통장 입금이 없을 경우 알람 발생
#################################################

require_once $_SERVER['DOCUMENT_ROOT'].'/_common/config.php';
include $_SERVER['DOCUMENT_ROOT']."/SMS/function_telegram.php";

## telegram define
define('BOT_TOKEN', '');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');
$_TELEGRAM_CHAT_ID = array('');
#$_TELEGRAM_CHAT_ID = array('');


# 동작하는 시간대 별로 비교시간 다르게 처리
# 00~01 : 은행에서 SMS 전송 중지 시간임
$time = date("H");
if ($time >= 00 && $time < 02) {
    $access = 100;
} elseif ($time >= 02 && $time < 07) {
    $access = 60;
} else {
    $access = 30;
}

$whereIs = "WHERE REG_DATE BETWEEN DATE_ADD(NOW(), INTERVAL -".$access." MINUTE) AND NOW()";
$query = "SELECT COUNT(*) AS CNT,  DATE_ADD(NOW(), INTERVAL -".$access." MINUTE) AS START_TIME, NOW() AS NOW_TIME FROM CASH_SMS_LOG ";
$query .= $whereIs;

$tmp = $db->get_data($query);
$num = $tmp["CNT"];
$start_time = $tmp["START_TIME"];
$now_time = $tmp["NOW_TIME"];

syslog(LOG_DEBUG, "==[SMS-MONITORING] ".$access."분전 수신 총 갯수 : ".$num." ==");

if ($tmp["CNT"] <= 0) {

  #최종 입금 시간
  $whereIs = "WHERE REG_DATE <= DATE_ADD(NOW(), INTERVAL -30 MINUTE)";
  $query = "SELECT SMS_DATE FROM CASH_SMS_LOG ";
  $query .= $whereIs;
  $query .= " ORDER BY REG_DATE DESC  LIMIT 1";
  $tmp			=	$db->get_data($query);

  ## 텔레그램 메시지
  $msg_tele = "🚨<b>입금 지연 알림</b>🚨\n";
  $msg_tele .= "최근 ".$access."분 동안 폰으로 입금 수신 내역이 없습니다.\n";
  $msg_tele .= "(".$start_time." ~ ".$now_time.")\n";
  $msg_tele .= "실제 은행에서 입금여부 체크가 필요합니다.\n\n";
  $msg_tele .= "최종 입금일시 : ".$tmp['SMS_DATE']."\n";
  $msg_tele .= "👉 <a href='https://supermmadmin010.suloko.com/order/payment_list_bank_SMS.html'>바로가기</a>\n";

  foreach ($_TELEGRAM_CHAT_ID AS $_TELEGRAM_CHAT_ID_STR) {
    $_TELEGRAM_QUERY_STR = array(
      'chat_id' => $_TELEGRAM_CHAT_ID_STR,
      'text'    => $msg_tele,
      'parse_mode' => "HTML"
    );
    telegramApiRequest("sendMessage", $_TELEGRAM_QUERY_STR);

    syslog(LOG_DEBUG, "==[SMS-MONITORING] Telegram push 완료 ==");
  }
}

# 기준 시간 마지막으로 수신(입금)된 내역
?>
