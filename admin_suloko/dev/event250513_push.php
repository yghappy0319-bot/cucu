<?php
/**_________________________________________________
 * 25/05/13 보너스 포인트 이벤트 푸시 개별 발송
 * - 최초 발송시 2시간 이상 소요되었으나 완료가 안되서 강제 종료
 * - 이후 발송시
 *         > wget 호출 방식에서 백그라운드 php 직접 실행으로 변경
 *         > 4개로 분산해서 처리
 *         > 30분 정도 소요됨
 * - 1000건 발송시 20분 정도 걸림
 * - crontab 설정 내용
            # 목요일(15),일요일(18) 19:30
            30 19 15,18 5 * /usr/bin/php /home/ubuntu/suloko-admin/dev/event250513_push_a1.php
            30 19 15,18 5 * /usr/bin/php /home/ubuntu/suloko-admin/dev/event250513_push_a2.php
            30 19 15,18 5 * /usr/bin/php /home/ubuntu/suloko-admin/dev/event250513_push_b1.php
            30 19 15,18 5 * /usr/bin/php /home/ubuntu/suloko-admin/dev/event250513_push_b2.php
            # 금요일(16),토요일(17),월요일(19) 15:00
            0 15 16,17,19 5 * /usr/bin/php /home/ubuntu/suloko-admin/dev/event250513_push_a1.php
            0 15 16,17,19 5 * /usr/bin/php /home/ubuntu/suloko-admin/dev/event250513_push_a2.php
            0 15 16,17,19 5 * /usr/bin/php /home/ubuntu/suloko-admin/dev/event250513_push_b1.php
            0 15 16,17,19 5 * /usr/bin/php /home/ubuntu/suloko-admin/dev/event250513_push_b2.php
            # 화요일(20) 15:00, 19:00
            0 15,19 20 5 * /usr/bin/php /home/ubuntu/suloko-admin/dev/event250513_push_a1.php
            0 15,19 20 5 * /usr/bin/php /home/ubuntu/suloko-admin/dev/event250513_push_a2.php
            0 15,19 20 5 * /usr/bin/php /home/ubuntu/suloko-admin/dev/event250513_push_b1.php
            0 15,19 20 5 * /usr/bin/php /home/ubuntu/suloko-admin/dev/event250513_push_b2.php
 * _________________________________________________
*/
ignore_user_abort(true);
set_time_limit(0); // 무제한 실행
ini_set('max_execution_time', 0);
ini_set('memory_limit', '1024M');

// 경로 지정
$path = $_SERVER["PATH_TRANSLATED"];
$arr = explode("/", $path);
array_pop($arr); //파일명 제거
array_pop($arr); //crond 경로 제거
$CROND_DOCUMENT_ROOT = implode("/", $arr);
unset($path, $arr);
$_SERVER["DOCUMENT_ROOT"] = $CROND_DOCUMENT_ROOT;
/* cron 실행시 기본 코드 */

require_once $_SERVER["DOCUMENT_ROOT"]."/_common/config.php";

##======================== 발송 내용 =========================
$title_a = "슈퍼빅 포인트업 이벤트";
$msg_a = "지금 슈로코에서 캐시를 충전하시면 50% 보너스 포인트를 추가 적립해드립니다.
단, 한번의 기회! 절대 놓치지 마세요!

▶ 캐시 충전시 50% 보너스 포인트 추가 지급

▶ 이벤트 기간: 5.13 ~ 5.20(7일간)";
##======================== 발송 내용 =========================

syslog(7, "EVENT-PUSH a1 start");
## 미결제자
$sql = "SELECT E.`USER_ID` FROM `MEMBER_EVENT_250513_a` AS E LEFT OUTER JOIN `MEMBER` M ON E.`USER_ID` = M.`USER_ID` WHERE `CASH_NO` IS NULL AND `TOKEN` = 'Y' LIMIT 0,3500";
$list = $db->get_list($sql);
// $list["USER_ID"] = ["jhwoo"];//, "kwshim91"];

$cnt = 0;
foreach($list["USER_ID"] as $userID) {
  // echo $cnt . ") " . $userID . "</BR>";
  sendNotification($userID, $title_a, $msg_a, $ICON_URL, $ACTION_URL);
  $cnt++;
}
syslog(7, "EVENT-PUSH a1 >> {$cnt}");
syslog(7, "EVENT-PUSH a1 end");


syslog(7, "EVENT-PUSH a2 start");
## 미결제자
$sql = "SELECT E.`USER_ID` FROM `MEMBER_EVENT_250513_a` AS E LEFT OUTER JOIN `MEMBER` M ON E.`USER_ID` = M.`USER_ID` WHERE `CASH_NO` IS NULL AND `TOKEN` = 'Y' LIMIT 3500,4000";
$list = $db->get_list($sql);
// $list["USER_ID"] = ["jhwoo"];//, "kwshim91"];

$cnt = 0;
foreach($list["USER_ID"] as $userID) {
  // echo $cnt . ") " . $userID . "</BR>";
  sendNotification($userID, $title_a, $msg_a, $ICON_URL, $ACTION_URL);
  $cnt++;
}
syslog(7, "EVENT-PUSH a2 >> {$cnt}");
syslog(7, "EVENT-PUSH a2 end");


##======================== 발송 내용 =========================
$title_b = "40% 보너스 추가 지급!";
$msg_b = "지금 슈로코에서 캐시를 충전하시면 40% 보너스 포인트를 추가 적립해드립니다.
단, 한번의 기회! 절대 놓치지 마세요!

▶ 캐시 충전시 40% 보너스 포인트 추가 지급

▶ 이벤트 기간: 5.13 ~ 5.20(7일간)";
##======================== 발송 내용 =========================

syslog(7, "EVENT-PUSH b12 start");
## 소액결제자
$sql = "SELECT E.`USER_ID` FROM `MEMBER_EVENT_250513_b1` AS E LEFT OUTER JOIN `MEMBER` M ON E.`USER_ID` = M.`USER_ID` WHERE `CASH_NO` IS NULL AND `TOKEN` = 'Y'";
$list = $db->get_list($sql);
// $list["USER_ID"] = ["jhwoo"];

$cnt = 0;
foreach($list["USER_ID"] as $userID) {
  // echo $cnt . ") " . $userID . "</BR>";
  sendNotification($userID, $title_b, $msg_b, $ICON_URL, $ACTION_URL);
  $cnt++;
}
syslog(7, "EVENT-PUSH b1 >> {$cnt}");

$sql = "SELECT E.`USER_ID` FROM `MEMBER_EVENT_250513_b2` AS E LEFT OUTER JOIN `MEMBER` M ON E.`USER_ID` = M.`USER_ID` WHERE `CASH_NO` IS NULL AND `TOKEN` = 'Y'";
$list = $db->get_list($sql);
// $list["USER_ID"] = ["jhwoo"];

$cnt = 0;
foreach($list["USER_ID"] as $userID) {
  // echo $cnt . ") " . $userID . "</BR>";
  sendNotification($userID, $title_b, $msg_b, $ICON_URL, $ACTION_URL);
  $cnt++;
}
syslog(7, "EVENT-PUSH b2 >> {$cnt}");
syslog(7, "EVENT-PUSH b12 end");


syslog(7, "EVENT-PUSH b3 start");
## 소액결제자
// echo "[{$title_b} - 3.3만이하]<br>";
$sql = "SELECT E.`USER_ID` FROM `MEMBER_EVENT_250513_b3` AS E LEFT OUTER JOIN `MEMBER` M ON E.`USER_ID` = M.`USER_ID` WHERE `CASH_NO` IS NULL AND `TOKEN` = 'Y'";
$list = $db->get_list($sql);
// $list["USER_ID"] = ["jhwoo"];

$cnt = 0;
foreach($list["USER_ID"] as $userID) {
  // echo $cnt . ") " . $userID . "</BR>";
  sendNotification($userID, $title_b, $msg_b, $ICON_URL, $ACTION_URL);
  $cnt++;
}
syslog(7, "EVENT-PUSH b3 >> {$cnt}");
syslog(7, "EVENT-PUSH b3 end");
