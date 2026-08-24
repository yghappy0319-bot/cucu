<?php
/**_________________________________________________
 * 추첨 결과 업데이트
 * - 12시부터 23시까지 매시 5분, 15분, 25분, 35분, 45분, 55분 / 일, 화, 수, 목, 토
 * _________________________________________________
 */
require_once $_SERVER['DOCUMENT_ROOT'].'/_common/config.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/_library/simple_html_dom.php';

// Constants
define('GATEWAY_PB', "http://110.234.19.236/gateway.php?url=");
define('GATEWAY_MM', "http://110.234.19.236/gateway_mm.php?url=");

/**
 * 추첨시간 이전에 실행시 이전 회차 데이터를 가지고 옴
 * 이때 PLAYDATE 값이 꼬이는 이슈가 확인되어 추첨시간 이후
 * 추첨일 13:10분 실행시 부터 로직이 적용되도록 체크함
 */

$now_date = date("Y-m-d H:i:s", time());
$chk_date = date("Y-m-d", time())." ".$draw_time_num.":05:00";
if (strtotime($now_date) < strtotime($chk_date)) {
  syslog(LOG_DEBUG, "추첨 시간이 아닙니다.");
  exit;
}

// 공통 사용 변수
$type    = null;
$drawnum = null;
$nums    = null;

// 추첨일
$now      = new DateTime();
$week     = (int)$now->format("w");
$hour     = (int)$now->format("H");
$playdate = $now->modify("-1 day")->format("Y-m-d");

// 수동 실행 여부
$pb_manual = false;
$mm_manual = false;

// 파워볼: 일요일, 화요일, 목요일, 12시부터 23시까지
if ((($week === 0 || $week === 2 || $week === 4) && ($hour >= 12 && $hour < 23)) || $pb_manual) {
  $type = "PB";
}

// 메가밀리언: 수요일, 토요일, 12시부터 23시까지
if ((($week === 3 || $week === 6 ) && ( $hour >= 12 && $hour < 23)) || $mm_manual) {
  $type = "MM";
}
echo 5;
if ($type !== null) {
  syslog(LOG_DEBUG, "========== Lottery Result Start =========>");
  syslog(LOG_DEBUG, "");

  // $sql = "
  //   SELECT
  //     (SELECT count(*) FROM super.WININFO WHERE GUBUN = 'PB' AND PLAYDATE = '2024-06-15' AND BALL1 IS NOT NULL) AS numbers,
  //     (SELECT count(*) FROM super.WININFO WHERE GUBUN = 'PB' AND PLAYDATE > '2024-06-15' AND PRIZ1 > 0) AS jackpot,
  //     (SELECT count(*) FROM super.ORDERS WHERE GUBUN = 'PB' AND PLAYDATE = '2024-06-15' AND WIN_MONEY_YN = 'Y') AS paid;
  // ";
  // $data = $db->get_data($sql);

  // $isNumbers = $data["numbers"]; // 추첨 번호
  // $isJackpot = $data["jackpot"]; // 다음 회차 당첨금
  // $isPaid    = $data["paid"]; // 당첨금 지급 여부

  // 회차 및 당첨번호 저장 유/무, 당첨금 조회
  $drawdata = getDrawdataByPlaydate($type, $playdate);
  $drawnum  = $drawdata["drawnum"];
  $isBall   = $drawdata["isBall"];
  $isPrize  = $drawdata["isPrize"];
  $isStatus = getNextPrize($type, $drawnum);

  syslog(LOG_DEBUG, "----------------------");
  syslog(LOG_DEBUG, "playdate: {$playdate}");
  syslog(LOG_DEBUG, "drawnum: {$drawnum}");
  syslog(LOG_DEBUG, "isBall: {$isBall}");
  syslog(LOG_DEBUG, "isPrize: {$isPrize}");
  syslog(LOG_DEBUG, "isStatus: {$isStatus}");
  syslog(LOG_DEBUG, "----------------------");
  syslog(LOG_DEBUG, "");

  // 추첨 번호 업데이트
  if ((int)$isBall === 1 || $isBall === true) {
    // 당첨 번호 조회 및 처리
    saveLotteryResults($type, $drawnum, $playdate);
  }

  // 추첨 결과 업데이트
  if ((int)$isStatus === 1 || $isStatus === true) {
    // 다음 회차 당첨금 조회
    $nextJackpot = getNextDrawJackpot($type, $playdate);

    // 다음 회차 당첨금 조회 성공 시
    if (!empty($nextJackpot)) {
      // 당첨 여부 및 다음 회차 당첨금 저장
      saveResultAndNextJackpot($type, $drawnum, $nextJackpot);
    }
  }

  // 등수별 당첨금 및 당첨자 수 업데이트
  if ((int)$isPrize === 1 || $isPrize === true) {
    // 당첨금 및 당첨자 수 조회
    $winning = getJackpotAndWinnerCount($type, $playdate);

    syslog(LOG_DEBUG, print_r($winning, true));

    if (!empty($winning)) {
      // 당첨금 및 당첨자 수 업데이트
      updateJackpotAndWinnerCount($type, $drawnum, $winning);
    }
  }

  syslog(LOG_DEBUG, "========== Lottery Result End =========>");
  syslog(LOG_DEBUG, "");
} else {
  syslog(LOG_DEBUG, "추첨일이 아닙니다.");
}

// 추첨일로 회차 및 당첨번호 유/무 확인
function getDrawdataByPlaydate($type, $playdate) {
  global $db;

  $sql = "
    SELECT
      DRAWNUM AS drawnum,
      IF(BALL1 IS NULL, true, false) AS isBall,
      IF(PRIZCNT9 <= 0, true, false) AS isPrize
    FROM WININFO
    WHERE GUBUN = '{$type}' AND PLAYDATE >= '{$playdate}'
    LIMIT 1
  ";
  return $db->get_data($sql);
}

// 다음 회차 당첨금 유/무 확인
function getNextPrize($type, $drawnum) {
  global $db;

  $drawnum = (int)$drawnum + 1;

  $sql = "
    SELECT IF(PRIZ1 <= 0, true, false) AS isPrize
    FROM WININFO
    WHERE GUBUN = '{$type}' AND DRAWNUM = '{$drawnum}'
    LIMIT 1
  ";
  return $db->get_data_one($sql);
}

// 당첨 번호 조회 및 저장
function saveLotteryResults($type, $drawnum, $playdate) {
  global $db, $html;

  if ($type === "PB") {
    $url  = "https://www.powerball.com/v1/gameapi/numbers?gamecode=powerball";
    $url  = GATEWAY_PB . urlencode($url);
    $html = file_get_html($url);

    // 추첨일
    $titleDate = $html->find(".title-date");
    $drawdate  = date("Y-m-d", strtotime($titleDate[0]->plaintext));

    // 당첨번호
    foreach ($html->find(".item-powerball") as $item) {
      $nums[] = $item->plaintext;
    }

    $html->clear();
  } else {
    $url = "https://www.megamillions.com/cmspages/utilservice.asmx/GetLatestDrawData";
    $url = GATEWAY_MM . urlencode($url);
    $data = callApi("GET", $url, "");

    if ($data[1] != 0) {
      echo_syslog($type, "megamillions api error - " . $data[2]);
    } else {
      $jsonTmp = json_decode($data[0], true);
      $json = json_decode($jsonTmp["d"], true);
      $draw = $json["Drawing"];

      // 추첨일
      $drawdate = date("Y-m-d", strtotime($draw["PlayDate"]));

      // 당첨 번호
      $nums = array($draw["N1"], $draw["N2"], $draw["N3"], $draw["N4"], $draw["N5"]);
      sort($nums);
      $nums[] = $draw["MBall"];
    }
  }

  // 당첨 번호 처리
  if ($playdate === $drawdate) {
    // 번호 저장
    saveLotteryNumber($type, $drawnum, $nums);

    // 번호 통계 저장
    saveLotteryNumberStatistics($type, $playdate, $drawnum, $nums);
  }
}

// 당첨 번호 저장
function saveLotteryNumber($type, $drawnum, $nums) {
  global $db;

  $sql = "
    UPDATE WININFO SET
      BALL1 = '{$nums[0]}',
      BALL2 = '{$nums[1]}',
      BALL3 = '{$nums[2]}',
      BALL4 = '{$nums[3]}',
      BALL5 = '{$nums[4]}',
      BALLP = '{$nums[5]}'
    WHERE GUBUN = '{$type}' AND  DRAWNUM = '{$drawnum}'
  ";
  $db->query($sql);
  syslog(LOG_DEBUG, "{$type}: 추첨 번호 저장 (saveLotteryNumber)");
}

// 당첨 번호 통계 저장
function saveLotteryNumberStatistics($type, $playdate, $drawnum, $nums) {
  global $db;

  $sql = "
    SELECT DRAWNUM, BALL1, BALL2, BALL3, BALL4, BALL5, BALLP
    FROM WINNING_NUMBER_STATISTIC
    WHERE GUBUN = '{$type}'
    ORDER BY DRAWNUM DESC
    LIMIT 1
  ";
  $lastInfo = $db->get_data($sql);

  if ($lastInfo["DRAWNUM"] < $drawnum) {
    $fromLast = [];
    $odd = $even = 0;
    $sections = array_fill_keys(range(10, 70, 10), 0);

    foreach ($nums as $key => $value) {
      if ($key > 4) {
        break;
      }

      // 홀수/짝수 카운트
      ($value % 2 == 1) ? $odd++ : $even++;

      // 섹션별 카운트
      $section = floor(($value - 1) / 10) * 10 + 10;
      $sections[$section]++;

      // 이전 당첨 번호와의 비교
      for ($i = 1; $i <= 5; $i++) {
        if ($value == $lastInfo["ball" . $i]) {
          $fromLast[] = $value;
        }
      }
    }

    $fromLastText = (empty($fromLast)) ? NULL : implode(",", $fromLast);

    $sql = "
      INSERT INTO WINNING_NUMBER_STATISTIC (
        PLAYDATE, GUBUN, DRAWNUM, BALL1, BALL2, BALL3, BALL4, BALL5, BALLP, FROM_LAST, ODD, EVEN, SECTION10, SECTION20, SECTION30, SECTION40, SECTION50, SECTION60, SECTION70
      ) VALUES (
        '{$playdate}',
        '{$type}',
        '{$drawnum}',
        '{$nums[0]}',
        '{$nums[1]}',
        '{$nums[2]}',
        '{$nums[3]}',
        '{$nums[4]}',
        '{$nums[5]}',
        ".(($fromLastText === NULL) ? 'NULL' : "'{$fromLastText}'").",
        '{$odd}',
        '{$even}',
        '{$sections[10]}',
        '{$sections[20]}',
        '{$sections[30]}',
        '{$sections[40]}',
        '{$sections[50]}',
        '{$sections[60]}',
        '{$sections[70]}'
      )
    ";
    $db->query($sql);
    syslog(LOG_DEBUG, "{$type}: 추첨 번호 통계 저장 (saveLotteryNumberStatistics)");
  }
}

// 다음 회차 당첨금 조회
function getNextDrawJackpot($type, $playdate) {
  if ($type === "PB") {
    $url  = "https://www.powerball.com/v1/gameapi/next-drawing?gamecode=powerball";
    $url  = GATEWAY_PB . urlencode($url);
    $html = file_get_html($url);

    // 다음 회차 추첨일
    $findDate     = $html->find(".title-date");
    $nextDrawdate = date("Y-m-d", strtotime($findDate[0]->plaintext));

    // 다음 회차 당첨금
    $findJackpot = $html->find(".game-jackpot-number");
    $jackpot     = $findJackpot[0]->plaintext;
    preg_match('/\d+/', $jackpot, $nextJackpot);

    if (strpos($jackpot, "Billion") == TRUE) {
      $nextJackpot = $nextJackpot[0] * 1000000000;
    } else if (strpos($jackpot, "Million") == TRUE) {
      $nextJackpot = $nextJackpot[0] * 1000000;
    }

    $html->clear();
  } else {
    $url = "https://www.megamillions.com/cmspages/utilservice.asmx/GetLatestDrawData";
    $url = GATEWAY_MM . urlencode($url);
    $data = callApi("GET", $url, "");

    if ($data[1] != 0) {
      syslog(LOG_DEBUG, "{$type} megamillions api error - " . $data[2]);
    } else {
      $jsonTmp = json_decode($data[0], true);
      $json = json_decode($jsonTmp["d"], true);

      $jackpot = $json["Jackpot"];
      $nextDrawdate = date("Y-m-d", strtotime($json["NextDrawingDate"]));
      $nextJackpot = $jackpot["NextPrizePool"];
    }
  }
  syslog(LOG_DEBUG, "{$type} api response [next-drawing] nextDrawdate={$nextDrawdate}, nextJackpot={$nextJackpot}");

  if (!isset($nextJackpot) || !isset($nextDrawdate)) {
    syslog(LOG_DEBUG, "{$type} 다음 회차 API 오류");
    return;
  }

  if (empty($nextJackpot) || $playdate >= $nextDrawdate) {
    syslog(LOG_DEBUG, "{$type} 다음 회차 예상 당첨금 업데이트 안됨");
    return;
  }

  return $nextJackpot;
}

// 당첨 여부 및 다음 회차 당첨금 저장
function saveResultAndNextJackpot($type, $drawnum, $nextJackpot) {
  global $db;

  $nextDrawnum = $drawnum + 1;
  $status = ($nextJackpot <= 20000000) ? "Y" : "N";

  if ($status === "Y") {
    $sql = "UPDATE WININFO SET IS_TYPE = '{$status}' WHERE GUBUN = '{$type}' AND DRAWNUM = '{$drawnum}'";
    $db->query($sql);
  }

  $sql = "UPDATE WININFO SET PRIZ1 = '{$nextJackpot}' WHERE GUBUN = '{$type}' AND DRAWNUM = '{$nextDrawnum}'";
  $db->query($sql);

  syslog(LOG_DEBUG, "{$type} 당첨 여부 및 다음 회차 당첨금 저장 (saveResultAndNextJackpot)");
}

// 당첨금 및 당첨자수 조회
function getJackpotAndWinnerCount($type, $playdate) {
  $baseUrl = "https://www.calottery.com/api/DrawGameApi/DrawGamePastDrawResults/";

  if ($type === "PB") {
    $url = GATEWAY_PB . urlencode($baseUrl . "12/1/20");
  } else if ($type === "MM") {
    $url = GATEWAY_PB . urlencode($baseUrl . "15/1/20");
  }

  $data = file_get_contents($url);
  $json = json_decode($data, true);
  $res  = $json["MostRecentDraw"];
  $json = null;

  $drawdate = date("Y-m-d", strtotime($res["DrawDate"]));

  if ($playdate != $drawdate) {
    syslog(LOG_DEBUG, "{$type} CALIFORNIA 당첨금 정보 업데이트 안됨");
    return;
  }

  $count = [];
  $amount = [];

  $prizes = $res["Prizes"];

  foreach ($prizes as $prize) {
    $count[] = $prize["Count"];
    $amount[] = $prize["Amount"];
  }

  if (count($count) < 9 && count($amount) < 9) {
    return;
  }

  return ["winningCount" => $count, "winningAmount" => $amount];
}

// 당첨금 및 당첨자 수 업데이트
function updateJackpotAndWinnerCount($type, $drawnum, $result) {
  global $db;

  $sql = "
    UPDATE WININFO
    SET
      PRIZ1 = '{$result["winningAmount"][0]}',
      PRIZ2 = '{$result["winningAmount"][1]}',
      PRIZ3 = '{$result["winningAmount"][2]}',
      PRIZ4 = '{$result["winningAmount"][3]}',
      PRIZ5 = '{$result["winningAmount"][4]}',
      PRIZ6 = '{$result["winningAmount"][5]}',
      PRIZ7 = '{$result["winningAmount"][6]}',
      PRIZ8 = '{$result["winningAmount"][7]}',
      PRIZ9 = '{$result["winningAmount"][8]}',
      PRIZCNT1 = '{$result["winningCount"][0]}',
      PRIZCNT2 = '{$result["winningCount"][1]}',
      PRIZCNT3 = '{$result["winningCount"][2]}',
      PRIZCNT4 = '{$result["winningCount"][3]}',
      PRIZCNT5 = '{$result["winningCount"][4]}',
      PRIZCNT6 = '{$result["winningCount"][5]}',
      PRIZCNT7 = '{$result["winningCount"][6]}',
      PRIZCNT8 = '{$result["winningCount"][7]}',
      PRIZCNT9 = '{$result["winningCount"][8]}'
    WHERE
      GUBUN = '{$type}' AND DRAWNUM = '{$drawnum}'
  ";
  $db->query($sql);

  syslog(LOG_DEBUG, "{$type} 당첨금 및 당첨자 수 업데이트 (updateJackpotAndWinnerCount)");
}
