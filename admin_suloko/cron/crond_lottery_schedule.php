<?php
/**_________________________________________________
 * 추첨 스케쥴 저장
 * - 년간 1회 (12월 1일 0시 0분)
 * _________________________________________________
*/
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

// 수동 실행 시
// $year = "2023";

if (!isset($year)) {
  $year = date("Y", strtotime("+1 years"));
}

// 입력기준일
$start_date = strtotime("{$year}-01-01");
$end_date   = strtotime("{$year}-12-31");

syslog(LOG_DEBUG, "========== Lottery Schedule Start =========>");
syslog(LOG_DEBUG, "");

syslog(LOG_DEBUG, "----------------------");
syslog(LOG_DEBUG, "start_date: {$start_date}");
syslog(LOG_DEBUG, "end_date: {$end_date}");
syslog(LOG_DEBUG, "----------------------");
syslog(LOG_DEBUG, "");

// 입력 기준일 전 마지막 회차 정보
$info = $db->get_data("SELECT MAX(DRAWNUM) AS DRAWNUM FROM WININFO WHERE GUBUN = 'PB' AND PLAYDATE < '{$year}-01-01'");
$pb_drawnum = $info['DRAWNUM'];
$info = $db->get_data("SELECT MAX(DRAWNUM) AS DRAWNUM FROM WININFO WHERE GUBUN = 'MM' AND PLAYDATE < '{$year}-01-01'");
$mm_drawnum = $info['DRAWNUM'];
unset($info);

for ($i = $start_date; $i < ($end_date + 86400 + 86400); $i += 86400) { // PLAYDATE 차이를 감안하여 최대 2일을 더 돌림
  $this_date = date("Y-m-d", $i);
  $weekNum   = date("w", $i);
  $PLAYDATE  = date("Y-m-d", $i-86400);
  $GUBUN     = "";

  // 썸머타임(DST) 체크
  if (check_dst($this_date)) {
    $add_time = 6+7;
  } else {
    $add_time = 7+7;
  }
  $TIME_S = $PLAYDATE . " 20:00:00";
  $TIME_E = date("Y-m-d H:00:00", strtotime("{$TIME_S} +{$add_time} hours"));

  if (strtotime($PLAYDATE) < $start_date) continue; // PLAYDATE 기준으로 start_date 체크

  // 당첨이 있는 날만 처리
  if ($weekNum == 0 || $weekNum == 2 || $weekNum == 4 ) {
    // 파워볼
    $pb_drawnum++;
    $DRAWNUM = $pb_drawnum;
    $GUBUN   = "PB";
  } else if ($weekNum == 3 || $weekNum == 6 ) {
    // 메가밀리언
    $mm_drawnum++;
    $DRAWNUM = $mm_drawnum;
    $GUBUN   = "MM";
  }

  // 게임 구분이 있을 경우만...
  if ($GUBUN != "") {
    $result = InsertWininfo($GUBUN, $PLAYDATE, $DRAWNUM, $TIME_S, $TIME_E);
    syslog(LOG_DEBUG, "[WININFO Insert] {$PLAYDATE} ({$GUBUN}) - result:{$result}");
  }

}

function InsertWininfo($GUBUN, $PLAYDATE, $DRAWNUM, $TIME_S, $TIME_E) {
  global $db;
  $query = "SELECT COUNT(*) AS CNT FROM WININFO WHERE GUBUN = '{$GUBUN}' AND PLAYDATE = '{$PLAYDATE}'";
  $info = $db->get_data($query);

  if ($info['CNT'] > 0) {
    return "pass";
  }

  $query = "
    INSERT INTO super.WININFO (
      GUBUN, PLAYDATE, DRAWNUM,TIME_S,TIME_E,YUTUBE,
      BALL1, BALL2, BALL3, BALL4, BALL5, BALLP,
      PRIZ1, PRIZ2, PRIZ3, PRIZ4, PRIZ5, PRIZ6, PRIZ7, PRIZ8, PRIZ9,
      PRIZCNT1, PRIZCNT2, PRIZCNT3, PRIZCNT4, PRIZCNT5, PRIZCNT6, PRIZCNT7, PRIZCNT8, PRIZCNT9,
      IS_TYPE, REG_DATE
    ) VALUES (
      '{$GUBUN}', '{$PLAYDATE}', '{$DRAWNUM}', '{$TIME_S}', '{$TIME_E}', NULL,
      NULL, NULL, NULL, NULL, NULL, NULL,
      0, 0, 0, 0, 0, 0, 0, 0, 0,
      0, 0, 0, 0, 0, 0, 0, 0, 0,
      'N', NOW()
    )
  ";
  $res = $db->query($query);
  return $res;
}
