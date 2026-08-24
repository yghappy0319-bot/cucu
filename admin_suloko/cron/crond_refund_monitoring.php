<?php
/**_________________________________________________
 * 환급 신청 건 모니터링
 * - 5분 마다
 * _________________________________________________
*/
require_once $_SERVER['DOCUMENT_ROOT'].'/_common/config.php';
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_INVOCE.php";

syslog(LOG_DEBUG, "========== Request for Refund Start =========>");
syslog(LOG_DEBUG, "");

// 체크일시
$add_query = " AND I.STATUS='N'";

$list = F_INVOCE_list(array(
  "row"         => 100,
  "page"        => 1,
  "order"       => "",
  "find_text"   => "",
  "find_object" => "",
  "add_query"   => $add_query
));

if ($list['total'] != 0) {
  $title   = "환급대기건 알림";
  $content = "{$list['total']}건이 미처리 중입니다.\n환급 처리를 완료해 주세요.";

  $result = sendFcmPush("refunds", $title, $content, $ICON_URL, $ACTION_URL);
  syslog(LOG_DEBUG, "[REFUNDS] {$list['total']}ea Push complete");
}

syslog(LOG_DEBUG, "========== Request for Refund End =========>");
syslog(LOG_DEBUG, "");
