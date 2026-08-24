<?php
/**_________________________________________________
 * 누적 당첨 정보 업데이트
 * - 13시부터 15시까지 매시 0분, 10분, 20분, 05분, 40분, 50분 / 일, 화, 수, 목, 토
 * _________________________________________________
 */
require_once $_SERVER['DOCUMENT_ROOT'].'/_common/config.php';
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_EXCHANGE.php";

$now   = new DateTime();
$today = $now->format('Y-m-d');
$week  = (int)$now->format('w');
$hour  = (int)$now->format('H');

// 일, 화, 수, 목, 토요일 13시부터 15시까지
$isMainWinners = in_array($week, [0, 2, 3, 4, 6]) && ($hour >= 13 && $hour < 15);

if ($isMainWinners) {
  syslog(LOG_DEBUG, "========== Main Winners Start =========>");
  syslog(LOG_DEBUG, "");

  syslog(LOG_DEBUG, "----------------------");
  syslog(LOG_DEBUG, "today: {$today}");
  syslog(LOG_DEBUG, "*week: {$week}");
  syslog(LOG_DEBUG, "*hour: {$hour}");
  syslog(LOG_DEBUG, "----------------------");
  syslog(LOG_DEBUG, "");

  $prevInfo = getLastMainAccumulatedInfo();
  $currentInfo = getOrdersWinInfo();

  if ($prevInfo && $prevInfo["createdAt"] < $today) {
    // 업데이트할 정보가 있는 경우 주요 누적 당첨 정보 업데이트 및 당첨 티켓 목록 저장
    updateMainAccumulatedWinInfo($prevInfo, $currentInfo);
  } else {
    syslog(LOG_DEBUG, "{$today} 데이터가 존재합니다.");
  }

  syslog(LOG_DEBUG, "========== Main Winners End =========>");
  syslog(LOG_DEBUG, "");
}

// 마지막 누적 정보 가져오기
function getLastMainAccumulatedInfo() {
  global $db;

  $sql = "SELECT WIN_MONEY, WIN_CNT, LEFT(CREATED_AT, 10) AS createdAt FROM MAIN_ACCUMULATE_INFO ORDER BY IDX DESC LIMIT 1";
  return $db->get_data($sql);
}

// 메인 누적 당첨 정보 업데이트
function updateMainAccumulatedWinInfo($prevInfo, $currentInfo) {
  global $db;

  $moneyDifferent = $prevInfo["WIN_MONEY"] !== $currentInfo["WIN_MONEY"];
  $countDifferent = $prevInfo["WIN_CNT"] !== $currentInfo["WIN_CNT"];

  if ($moneyDifferent || $countDifferent) {
    // 메인 누적 당첨 정보 업데이트
    saveMainAccumulatedWinInfo();
    // 메인 당첨 티켓 목록 저장
    saveWinnerTicketList();
  } else {
    syslog(LOG_DEBUG, "업데이트 할 정보가 없습니다.");
  }
}

// 주문 당첨 정보 가져오기
function getOrdersWinInfo() {
  global $db;

  $sql = "SELECT SUM(WIN_MONEY) AS WIN_MONEY, COUNT(*) AS WIN_CNT FROM ORDERS WHERE WIN_YN = 'Y' AND WIN_MONEY_YN = 'Y'";
  return $db->get_data($sql);
}

// 메인 누적 당첨 정보 저장
function saveMainAccumulatedWinInfo() {
  global $db;
  syslog(LOG_DEBUG, "메인 누적 당첨 정보 저장");

  $sql = "
    INSERT INTO MAIN_ACCUMULATE_INFO (WIN_MONEY, WIN_CNT)
    SELECT SUM(WIN_MONEY) AS WIN_MONEY, COUNT(*) AS WIN_CNT
    FROM ORDERS
    WHERE WIN_YN = 'Y' AND WIN_MONEY_YN = 'Y'
  ";
  $db->query($sql);
}

// 메인 당첨 티켓 목록 저장
function saveWinnerTicketList() {
  global $db;
  syslog(LOG_DEBUG, "메인 당첨 티켓 목록 저장");

  $sql = "DELETE FROM MAIN_WINNER_LIST";
  $db->query($sql);

  $sql = "
    INSERT INTO
      MAIN_WINNER_LIST (ID, USER_ID, NAME, FNAME, LNAME, IMG_URL, WIN1, WIN2, WIN3, WIN4, WIN5, WIN_MONEY)
    (
      SELECT
        O.USER_ID AS ID,
        CONCAT(LEFT(O.USER_ID, 3), REPEAT('*', LENGTH(O.USER_ID) - 3)) AS USER_ID,
        M.NAME AS NAME,
        LEFT(M.NAME, 1) AS FNAME,
        RIGHT(M.NAME, 1) AS LNAME,
        CONCAT(SUBSTRING(REPLACE(O.IMG_DATE, '-', ''), 3, 8), '/', O.IMG_PATH) AS IMG_URL,
        O.WIN1 AS WIN1,
        O.WIN2 AS WIN2,
        O.WIN3 AS WIN3,
        O.WIN4 AS WIN4,
        O.WIN5 AS WIN5,
        O.WIN_MONEY_USD AS WIN_MONEY
      FROM
        ORDERS AS O
        INNER JOIN MEMBER AS M ON M.USER_ID = O.USER_ID
      WHERE
        O.WIN_MONEY_USD >= 500
        AND O.USER_ID NOT IN ('aks44193451', 'joonjoon79sss@gmail.com', 'jwcyoointai')
      ORDER BY O.REG_DATE DESC
    )
    UNION
    (
      SELECT
        O.USER_ID AS ID,
        CONCAT(LEFT(O.USER_ID, 3), REPEAT('*', LENGTH(O.USER_ID) - 3)) AS USER_ID,
        M.NAME AS NAME,
        LEFT(M.NAME, 1) AS FNAME,
        RIGHT(M.NAME, 1) AS LNAME,
        CONCAT(SUBSTRING(REPLACE(O.IMG_DATE, '-', ''), 3, 8), '/', O.IMG_PATH) AS IMG_URL,
        O.WIN1 AS WIN1,
        O.WIN2 AS WIN2,
        O.WIN3 AS WIN3,
        O.WIN4 AS WIN4,
        O.WIN5 AS WIN5,
        O.WIN_MONEY_USD AS WIN_MONEY
      FROM
        ORDERS AS O
        INNER JOIN MEMBER AS M ON M.USER_ID = O.USER_ID
      WHERE
        (WIN1 IN (1,2,3,4,5) OR
        WIN2 IN (1,2,3,4,5) OR
        WIN3 IN (1,2,3,4,5) OR
        WIN4 IN (1,2,3,4,5) OR
        WIN5 IN (1,2,3,4,5))
        AND O.WIN_YN = 'Y'
        AND O.WIN_MONEY_YN = 'Y'
        AND O.USER_ID NOT IN ('aks44193451', 'joonjoon79sss@gmail.com', 'jwcyoointai')
      ORDER BY O.REG_DATE DESC
      LIMIT 20
    )
  ";
  $db->query($sql);
}
