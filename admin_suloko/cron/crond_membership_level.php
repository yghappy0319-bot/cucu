<?php
/**_________________________________________________
 * 회원 레벨 업데이트
 * - 0시, 6시, 12시 18시
 * _________________________________________________
 */
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

// 실행일
$now   = new DateTime();
$today = $now->format('Y-m-d');

syslog(LOG_DEBUG, "========== Membership Level Start =========>");
syslog(LOG_DEBUG, "");

syslog(LOG_DEBUG, "----------------------");
syslog(LOG_DEBUG, "today: {$today}");
syslog(LOG_DEBUG, "----------------------");
syslog(LOG_DEBUG, "");

$list = $db->get_list("SELECT SUM(CASH) AS CASH, USER_ID FROM ORDERS WHERE SIGN_YN != 'C' GROUP BY USER_ID");

$cashArray = $list["CASH"];
$userIds = $list["USER_ID"];

$total = count($cashArray);

for ($i = 0; $i < $total; $i++) {
  $cash = $cashArray[$i];
  $user_id = $userIds[$i];

  $member = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID = '{$user_id}'");

  if (!isset($member["USER_ID"])) {
    continue;
  }

  $new_level = 1;
  if ($cash >= 500000 && $cash < 1000000) {
    $new_level = 2;
  } else if ($cash >= 1000000 && $cash < 2000000) {
    $new_level = 3;
  } else if ($cash >= 2000000) {
    $new_level = 4;
  }

  if ($member['LEVEL'] < $new_level) {
    // syslog(LOG_DEBUG, $user_id . ": " . $cash);
    // syslog(LOG_DEBUG, $member["LEVEL"] . " -> " . $new_level);
    $db->query("UPDATE MEMBER SET LEVEL='{$new_level}' WHERE USER_ID = '{$user_id}'");
  }
}

syslog(LOG_DEBUG, "========== Membership Level End =========>");
syslog(LOG_DEBUG, "");
