<?php
/**
 * 당첨 결과 및 당첨금 지급 프로세스
 * suloko AWS에서 실행시킬 파일
 */

require_once $_SERVER['DOCUMENT_ROOT'].'/_common/config.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/_library/simple_html_dom.php';

// 공통 사용 변수
$type    = null;
$drawnum = null;
$nums    = null;

// 추첨일
$now      = new DateTime();
$week     = (int)$now->format("w");
$hour     = (int)$now->format("G");
$playdate = $now->modify("-1 day")->format("Y-m-d");
$regdate  = "2024-07-20 02:00:00";

// 수동 실행 여부
$pb_manual = false;
$mm_manual = false;

// 파워볼: 일요일, 화요일, 목요일, 12시부터 23시까지
if ((in_array($week, [0, 2, 4]) && ($hour >= 11 && $hour < 23)) || $pb_manual) {
  $type = "PB";
}

// 메가밀리언: 수요일, 토요일, 12시부터 23시까지
if ((in_array($week, [3, 6]) && ($hour >= 11 && $hour < 23)) || $mm_manual) {
  $type = "MM";
}

if ($type !== null) {
  syslog(LOG_DEBUG, "========== Lottery Result Start =========>");
  syslog(LOG_DEBUG, "");

  $drawdata = getDrawInfoByDate($type, $playdate);
  $drawnum  = $drawdata["drawnum"];
  $isNumber = $drawdata["isDrawNumbers"];

  syslog(LOG_DEBUG, "----------------------");
  syslog(LOG_DEBUG, "type: {$type}");
  syslog(LOG_DEBUG, "playdate: {$playdate}");
  syslog(LOG_DEBUG, "drawnum: {$drawnum}");
  syslog(LOG_DEBUG, "regdate: {$regdate}");
  syslog(LOG_DEBUG, "isNumber: {$isNumber}");
  syslog(LOG_DEBUG, "----------------------");
  syslog(LOG_DEBUG, "");

  if ((int) $isNumber > 0) {
    // 주문 건 처리
    setOrders($type, $playdate, $regdate);
  } else {
    syslog(LOG_DEBUG, "추첨 번호 업데이트 전으로 작업 종료");
    syslog(LOG_DEBUG, "========== Lottery Result End =========>");
    syslog(LOG_DEBUG, "");
    return false;
  }

  syslog(LOG_DEBUG, "========== Lottery Result End =========>");
  syslog(LOG_DEBUG, "");
} else {
  syslog(LOG_DEBUG, "추첨일 또는 추첨 시간이 아니므로 작업 종료");
  syslog(LOG_DEBUG, "========== Lottery Result End =========>");
  syslog(LOG_DEBUG, "");
}

// 추첨 정보 가져오기
function getDrawInfoByDate($type, $playdate) {
  global $db;

  $sql = "
    SELECT
      DRAWNUM AS drawnum,
      IF(BALL1 IS NULL, false, true) AS isDrawNumbers
    FROM WININFO
    WHERE GUBUN = '{$type}' AND PLAYDATE = '{$playdate}'
    LIMIT 1
  ";
  return $db->get_data($sql);
}

function setOrders($type, $playdate, $regdate) {
  global $db;

  syslog(LOG_DEBUG, "processing...");
  syslog(LOG_DEBUG, "");

  // 최신 환율 조회
  $exchange = $db->get_data_one("SELECT WON FROM EXCHANGE ORDER BY EXCHANGE_NO DESC LIMIT 1");
  syslog(LOG_DEBUG, "환율: " . $exchange);

  // 추첨 번호 조회
  $wininfo = $db->get_data("SELECT * FROM WININFO WHERE GUBUN = '{$type}' AND PLAYDATE = '{$playdate}'");

  // 추첨 번호 저장
  $winD = array();
  $winP = array();
  $winD[0] = intval($wininfo['BALL1']);
  $winD[1] = intval($wininfo['BALL2']);
  $winD[2] = intval($wininfo['BALL3']);
  $winD[3] = intval($wininfo['BALL4']);
  $winD[4] = intval($wininfo['BALL5']);
  $winP[0] = intval($wininfo['BALLP']);

  // 당첨금 처리 대상 주문 건 조회
  $orders = $db->get_list("SELECT * FROM ORDERS WHERE GUBUN = '{$type}' AND PLAYDATE = '{$playdate}' AND REG_DATE < '{$regdate}' AND WIN_MONEY_YN NOT IN ('Y')");
  syslog(LOG_DEBUG, "건수: " . count($orders["ORDERS_NO"]));

  // 대상 주문 건이 존재 하지 않을 시 종료
  if (!isset($orders["ORDERS_NO"])) {
    return false;
  }
  $cnt = 0;

  for ($i = 0; $i < count($orders["ORDERS_NO"]); $i++) {
    $winMoneyUSD = 0;
    $win = array(0, 0, 0, 0, 0, 0);

    for ($s = 1; $s <= 5; $s++) {
      if (!isset($orders["BALL".$s][$i])) {
        continue;
      }

      if ($orders["BALL".$s][$i] != "") {
        $winCntD = 0;
        $winCntP = 0;

        $ball = $orders["BALL".$s][$i];
        $balls = explode(",", $ball);
        $ballD = array($balls[0], $balls[1], $balls[2], $balls[3], $balls[4]);
        $ballP = array($balls[5]);

        if ($winP[0] == $balls[5]) {
          $winCntP++;
        }

        for ($k = 0; $k < sizeof($ballD); $k++) {
          if (in_array($ballD[$k], $winD)) {
            $winCntD++;
          }
        }

        if ($winCntD == 5 && $winCntP == 1) {
          $rank = 1;
        } else if ($winCntD == 5 && $winCntP == 0) {
          $rank = 2;
        } else if ($winCntD == 4 && $winCntP == 1) {
          $rank = 3;
        } else if ($winCntD == 4 && $winCntP == 0) {
          $rank = 4;
        } else if ($winCntD == 3 && $winCntP == 1) {
          $rank = 5;
        } else if ($winCntD == 3 && $winCntP == 0) {
          $rank = 6;
        } else if ($winCntD == 2 && $winCntP == 1) {
          $rank = 7;
        } else if ($winCntD == 1 && $winCntP == 1) {
          $rank = 8;
        } else if ($winCntD == 0 && $winCntP == 1) {
          $rank = 9;
        } else {
          $rank = 0;
        }

        $prize = $wininfo["PRIZ" . $rank] ?? 0;

        if ($prize == "") {
          $prize = 0;
        }

        $winMoneyUSD += $prize;
        $win[$s] = $rank;
      }
    }

    $winMoney = $winMoneyUSD * $exchange;
    $winYn = ($winMoney > 0) ? "Y" : "N";

    $sql = "
      UPDATE ORDERS SET
        WIN1          = '{$win[1]}',
        WIN2          = '{$win[2]}',
        WIN3          = '{$win[3]}',
        WIN4          = '{$win[4]}',
        WIN5          = '{$win[5]}',
        WIN_MONEY     = '{$winMoney}',
        WIN_MONEY_USD = '{$winMoneyUSD}',
        WON           = '{$exchange}',
        WIN_YN        = '{$winYn}',
        WIN_MONEY_YN  = 'Y'
      WHERE
        ORDERS_NO = '{$orders['ORDERS_NO'][$i]}'
    ";
    $db->query($sql);

    if ($winMoney > 0) {
      $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID = '{$orders['USER_ID'][$i]}'");

      if ($mem["CASH"] == "") {
        $mem["CASH"] = 0;
      }
      if ($mem["WINCASH"] == "") {
        $mem["WINCASH"] = 0;
      }

      $n_wcash = $mem["WINCASH"] + $winMoney;
      $o_wcash = $mem["WINCASH"];

      $sql = "
        INSERT INTO T_WCASH_LOG(
          ORDERS_NO,
          WCASH,
          N_WCASH,
          O_WCASH,
          USER_ID,
          MEMO,
          STATUS
        ) VALUES (
          '{$orders['ORDERS_NO'][$i]}',
          '{$winMoney}',
          '{$n_wcash}',
          '{$o_wcash}',
          '{$orders['USER_ID'][$i]}',
          '당첨금적립',
          'P'
        )
      ";
      $db->query($sql);

      $db->query("UPDATE MEMBER SET WINCASH = WINCASH + '{$winMoney}' WHERE USER_ID = '{$orders['USER_ID'][$i]}'");

      // 당첨 등수 텍스트 처리
      $win_rank = array();
      for ($rank_cnt = 1 ; $rank_cnt <=5 ; $rank_cnt++) {
        if ($win[$rank_cnt] > 0) array_push($win_rank, $win[$rank_cnt]."등");
      }
      $win_rank_str = implode(",", $win_rank);

      // 당첨자 문자 발송
      if ($price > 600) { // 600달러 이상 관리자에게 전송
        $to_arr = array();
        foreach ($to_arr as $to) {
          $from        = $fromHP;
          $CODESK      = "S";
          $TEMPLET_NO  = "";
          $MEMBER_NO   = "";
          $MEMBER_NAME = "";
          $SUBJECT     = "고액당첨확인";
          $SENDMSG     = "고액 당첨이 확인되었습니다.\r\n\r\n";
          $SENDMSG     .= "회원 : {$mem['NAME']} ({$mem['USER_ID']})\r\n";
          $SENDMSG     .= "게임 : {$ball_op[$list["GUBUN"][$i]]} {$list["DRAWNUM"][$i]}회차\r\n";
          $SENDMSG     .= "등수 : {$win_rank_str}\r\n";
          $SENDMSG     .= "금액 : ".number_format($won_price)."원 (".number_format($price)."달러)";

          $sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG);
        }
      } else { // 회원에게 전송
        $TEMPLET_NO  = 17;
        $SMSTEMPLET  = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='{$TEMPLET_NO}'");
        $from        = $fromHP;
        $to          = $list["HP"][$i];
        $CODESK      = "S";
        $MEMBER_NO   = $mem['MEMBER_NO'];
        $MEMBER_NAME = $mem['NAME'];
        $SUBJECT     = $SMSTEMPLET['SUBJECT'];
        $SENDMSG     = $SMSTEMPLET['CONTENT'];
        $SENDMSG     = str_replace('{USERID}', $mem['USER_ID'], $SENDMSG); // 아이디
        $SENDMSG     = str_replace('{USERNAME}', $mem['NAME'], $SENDMSG); // 이름
        $SENDMSG     = str_replace('{REGDATE}', $list["REG_DATE"][$i], $SENDMSG); // 구매일
        $SENDMSG     = str_replace('{LOTTONAME}', $ball_op[$list["GUBUN"][$i]], $SENDMSG); // 게임구분
        $SENDMSG     = str_replace('{WINRANK}', $win_rank_str, $SENDMSG); // 당첨등수
        $SENDMSG     = str_replace('{WINMONEY}', number_format($won_price)."원", $SENDMSG); // 당첨금액
        $SENDMSG     = addslashes($SENDMSG);

        $sms = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG);
      }
    }
  }
}
