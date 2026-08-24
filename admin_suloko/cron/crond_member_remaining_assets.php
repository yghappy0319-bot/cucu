<?php
/**_________________________________________________
 * 월별 회원 잔여 자산금액 저장
 * - 매월 1일 0시 0분
 * _________________________________________________
*/
require_once $_SERVER['DOCUMENT_ROOT'].'/_common/config.php';

syslog(LOG_DEBUG, "========== Member Remaining Assets Start =========>");
syslog(LOG_DEBUG, "");

// 실행 시점의 회원 자산 합계
$sql = "
  INSERT INTO REMAINING_AMOUNT
    (CASH, WINCASH, IPOINT, POINT)
  SELECT
    SUM(`CASH`) AS `CASH`, SUM(`WINCASH`) AS `WINCASH`, SUM(`IPOINT`) AS `IPOINT`, SUM(`POINT`) AS `POINT` FROM `MEMBER`";
$db->query($sql);

syslog(LOG_DEBUG, "========== Member Remaining Assets End =========>");
syslog(LOG_DEBUG, "");
