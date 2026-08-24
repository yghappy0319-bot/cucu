<?php
/**_________________________________________________
 * 추첨 결과 업데이트
 * - 12시부터 23시까지 매시 5분, 15분, 25분, 35분, 45분, 55분 / 일, 화, 수, 목, 토
 * _________________________________________________
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

// 수동 실행 여부
$pb_manual = false;
$mm_manual = false;


$db->query("insert into CRON_LOG set FILE = '추첨결과진행', REG_DATE = now() ");

// 파워볼: 일요일, 화요일, 목요일, 12시부터 23시까지
if ((($week === 0 || $week === 2 || $week === 4) && ($hour >= 12 && $hour < 23)) || $pb_manual) {
    $type = "PB";
}

// 메가밀리언: 수요일, 토요일, 12시부터 23시까지
if ((($week === 3 || $week === 6 ) && ( $hour >= 12 && $hour < 23)) || $mm_manual) {
    $type = "MM";
}

if ($type !== null) {

  syslog(LOG_DEBUG, "========== Lottery Result Start =========>");
  syslog(LOG_DEBUG, "");

  // 추첨 정보 가져오기
  $drawdata      = getDrawInfoByDate($type, $playdate);
  $drawnum       = $drawdata["drawnum"];
  $isDrawNumbers = $drawdata["isDrawNumbers"]; // 추첨 번호 등록 여부
  $isNextJackpot = $drawdata["isNextJackpot"]; // 다음 회차 당첨금 등록 여부
  $isNumberOfWin = $drawdata["isNumberOfWin"]; // 등수별 당첨금 등록 여부

  syslog(LOG_DEBUG, "----------------------");
  syslog(LOG_DEBUG, "*****playdate: {$playdate}");
  syslog(LOG_DEBUG, "******drawnum: {$drawnum}");
  syslog(LOG_DEBUG, "isDrawNumbers: {$isDrawNumbers}");
  syslog(LOG_DEBUG, "isNumberOfWin: {$isNumberOfWin}");
  syslog(LOG_DEBUG, "isNextJackpot: {$isNextJackpot}");
  syslog(LOG_DEBUG, "----------------------");
  syslog(LOG_DEBUG, "");

  // 추첨 번호 업데이트
  if ((int)$isDrawNumbers === 0 || $isDrawNumbers === false) {
    // 당첨 번호 조회 및 처리
    saveLotteryResults($type, $drawnum, $playdate);
  }

  // 추첨 결과 업데이트 (다음 회차 당첨금, 현재 회차 당첨/이월 여부)
  //if ((int)$isNextJackpot === 0 || $isNextJackpot === false) {
    // 다음 회차 당첨금 조회
    $nextJackpot = getNextDrawJackpot($type, $playdate);

    // 다음 회차 당첨금 조회 성공 시
    if (!empty($nextJackpot)) {
      // 당첨 여부 및 다음 회차 당첨금 저장
      saveResultAndNextJackpot($type, $drawnum, $nextJackpot);
    }
  //}

  // 등수별 당첨자 수 업데이트
  if ((int)$isNumberOfWin === 0 || $isNumberOfWin === false) {
    // 당첨자 수 조회
    $oregonCounts = getOregonWinnersCount($type, $playdate);

    if (!empty($oregonCounts)) {
      // 당첨자 수 업데이트
      updateOregonWinnerCount($type, $drawnum, $oregonCounts);
    }
  }

  syslog(LOG_DEBUG, "========== Lottery Result End =========>");
  syslog(LOG_DEBUG, "");
} else {
  syslog(LOG_DEBUG, "추첨일이 아닙니다.");
}

// 추첨 결과 API 결과 가져오기
function getDrawDataFromApi($url) {
  return file_get_html($url);
}

// 추첨일로 추첨 정보 가져오기
function getDrawInfoByDate($type, $playdate) {
  global $db;

  $sql = "
    SELECT
      DRAWNUM AS drawnum,
      IF(BALL1 IS NULL, false, true) AS isDrawNumbers,
      IF(PRIZCNT9 <= 0, false, true) AS isNumberOfWin,
      (SELECT IF(PRIZ1 <= 0, false, true) FROM WININFO WHERE GUBUN = '{$type}' AND PLAYDATE > '{$playdate}' LIMIT 1) AS isNextJackpot
    FROM WININFO
    WHERE GUBUN = '{$type}' AND PLAYDATE = '{$playdate}'
    LIMIT 1
  ";
echo $sql;
  return $db->get_data($sql);
}

// 추첨 번호 처리
function saveLotteryResults($type, $drawnum, $playdate) {
  $url = ($type === "PB") ?
    "https://www.powerball.com/v1/gameapi/numbers?gamecode=powerball" :
    "https://www.megamillions.com/cmspages/utilservice.asmx/GetLatestDrawData";

  $html = getDrawDataFromApi($url);

  if ($type === "PB") {
    // 추첨일
    $titleDate = $html->find(".title-date");
    $drawdate  = date("Y-m-d", strtotime($titleDate[0]->plaintext));

    // 추첨 번호
    foreach ($html->find(".item-powerball") as $item) {
      $nums[] = $item->plaintext;
    }
    $multi = str_replace("x", "", $html->find(".multiplier")[0]->plaintext);
  } else {
    $xml        = simplexml_load_string($html);
    $jsonString = (string)$xml;
    $jsonArray  = json_decode($jsonString, true);
    $drawing    = $jsonArray["Drawing"];

    // 추첨일
    $drawdate = date("Y-m-d", strtotime($drawing["PlayDate"]));

    // 추첨 번호
    $nums   = [$drawing["N1"], $drawing["N2"], $drawing["N3"], $drawing["N4"], $drawing["N5"]];
    $nums[] = $drawing["MBall"];
    $multi  = 0;
  }

  // 초기화
  $html->clear();

  if ($playdate === $drawdate) {
    // 추첨 번호 업데이트
    saveLotteryNumber($type, $drawnum, $nums, $multi);

    // 추첨 번호 통계 저장
    saveLotteryNumberStatistics($type, $playdate, $drawnum, $nums);
  }
}

// 추첨 번호 업데이트
function saveLotteryNumber($type, $drawnum, $nums, $multi) {
  global $db, $megaPrizes, $powerPrizes;

  // 등수별 당첨금
  $prizes = ($type === "MM") ? $megaPrizes : $powerPrizes;

  $sql = "
    UPDATE WININFO SET
      PRIZ2 = '{$prizes[2]}',
      PRIZ3 = '{$prizes[3]}',
      PRIZ4 = '{$prizes[4]}',
      PRIZ5 = '{$prizes[5]}',
      PRIZ6 = '{$prizes[6]}',
      PRIZ7 = '{$prizes[7]}',
      PRIZ8 = '{$prizes[8]}',
      PRIZ9 = '{$prizes[9]}',
      BALL1 = '{$nums[0]}',
      BALL2 = '{$nums[1]}',
      BALL3 = '{$nums[2]}',
      BALL4 = '{$nums[3]}',
      BALL5 = '{$nums[4]}',
      BALLP = '{$nums[5]}',
      MULTI = '{$multi}'
    WHERE GUBUN = '{$type}' AND  DRAWNUM = '{$drawnum}'
  ";
  echo $sql;
  $db->query($sql);
  syslog(LOG_DEBUG, "{$type}: 추첨 번호 업데이트 완료");
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
        if ($value == $lastInfo["BALL" . $i]) {
          $fromLast[] = $value;
        }
      }
    }

    $fromLastText = (empty($fromLast)) ? NULL : implode(",", $fromLast);

    $sql = "
      INSERT INTO WINNING_NUMBER_STATISTIC (
        PLAYDATE, GUBUN, DRAWNUM, BALL1, BALL2, BALL3, BALL4, BALL5, BALLP, FROM_LAST, ODD, EVEN,
        SECTION10, SECTION20, SECTION30, SECTION40, SECTION50, SECTION60, SECTION70
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
    syslog(LOG_DEBUG, "{$type}: 추첨 번호 통계 저장 완료");
  }
}

// 다음 회차 당첨금 조회
function getNextDrawJackpot($type, $playdate) {
  $url = ($type === "PB") ?
    "https://www.powerball.com/v1/gameapi/next-drawing?gamecode=powerball" :
    "https://www.megamillions.com/cmspages/utilservice.asmx/GetLatestDrawData";

  $html = getDrawDataFromApi($url);

  if ($type === "PB") {
    // 다음 회차 추첨일
    $findDate     = $html->find(".title-date");
    $nextDrawdate = date("Y-m-d", strtotime($findDate[0]->plaintext));

    // 다음 회차 당첨금
    $findJackpot = $html->find(".game-jackpot-number");
    $jackpot     = $findJackpot[0]->plaintext;

    // 숫자와 소수점까지 추출
    preg_match('/[\d.]+/', $jackpot, $matches);

    // 문자열에 Billion 또는 Million이 포함되는지 정확히 검사
    if (strpos($jackpot, "Billion") !== false) {
      $nextJackpot = floatval($matches[0]) * 1000000000;
    } else if (strpos($jackpot, "Million") !== false) {
      $nextJackpot = floatval($matches[0]) * 1000000;
    } else {
      $nextJackpot = 0; // 또는 기본값 처리
    }

  } else {
    $xml       = simplexml_load_string($html);
    $xmlString = (string)$xml;
    $json      = json_decode($xmlString, true);

    // 다음 회차 추첨일
    $nextDrawdate = date("Y-m-d", strtotime($json["NextDrawingDate"]));

    // 다음 회차 당첨금
    $jackpot     = $json["Jackpot"];
    $nextJackpot = ($jackpot["Verified"]) ? $jackpot["NextPrizePool"] : "";
  }

  // 초기화
  $html->clear();
  echo $nextJackpot."<Br>";

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

// 당첨/이월 여부 및 다음 회차 당첨금 저장
function saveResultAndNextJackpot($type, $drawnum, $nextJackpot) {
  global $db, $new_megamillion_draw_date;

  $nextDrawnum = $drawnum + 1;
  if ($type === "MM" && getSecondDifference($new_megamillion_draw_date) >= 0) {
    $status = ($nextJackpot <= 50000000) ? "Y" : "N"; //메가밀리언 초기화 금액 변경
  } else {
    $status = ($nextJackpot <= 20000000) ? "Y" : "N";
  }

  if ($status === "Y") {
    $sql = "UPDATE WININFO SET IS_TYPE = '{$status}' WHERE GUBUN = '{$type}' AND DRAWNUM = '{$drawnum}'";
    $db->query($sql);
  }

  $sql = "UPDATE WININFO SET PRIZ1 = '{$nextJackpot}' WHERE GUBUN = '{$type}' AND DRAWNUM = '{$nextDrawnum}'";
  $db->query($sql);

  syslog(LOG_DEBUG, "{$type} 당첨/이월 여부 및 다음 회차 당첨금 업데이트 완료");
}

// 당첨자 수 조회 (oregon)
function getOregonWinnersCount($type, $playdate) {
  $formattedDate = date("n/d/y", strtotime($playdate));

  $baseUrl = "https://api2.oregonlottery.org/drawresults/ByDrawDate";

  $queryParams = [
    "gameSelector" => $type,
    "startingDate" => $formattedDate,
    "endingDate"   => $formattedDate,
    "includeOpen"  => "False"
  ];

  $url = $baseUrl . "?" . http_build_query($queryParams);

  $headers = [
    "Ocp-Apim-Subscription-Key: 683ab88d339c4b22b2b276e3c2713809",
    "Content-Type: application/json"
  ];

  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL            => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 10,
  ]);

  $response = curl_exec($ch);
  curl_close($ch);

  $data = json_decode($response, true);
  // $oregonJackpotWinners = $data[0]['OregonJackpotWinners'];
  $oregonJackpotWinners = 0;
  $oregonShareCounts = $data[0]['OregonShareCounts'];
  $oregonShareCounts = array_slice($oregonShareCounts, 0, 8);
  array_unshift($oregonShareCounts, $oregonJackpotWinners);

  return $oregonShareCounts;
}

// 당첨금 및 당첨자 수 업데이트
function updateOregonWinnerCount($type, $drawnum, $winCnts) {
  global $db;

  if (array_sum($winCnts) === 0) {
    syslog(LOG_DEBUG, "{$type} > 당첨자 수 업데이트 안됨");
    return;
  }

  $sql = "
    UPDATE WININFO
    SET
      PRIZCNT1 = '{$winCnts[0]}',
      PRIZCNT2 = '{$winCnts[1]}',
      PRIZCNT3 = '{$winCnts[2]}',
      PRIZCNT4 = '{$winCnts[3]}',
      PRIZCNT5 = '{$winCnts[4]}',
      PRIZCNT6 = '{$winCnts[5]}',
      PRIZCNT7 = '{$winCnts[6]}',
      PRIZCNT8 = '{$winCnts[7]}',
      PRIZCNT9 = '{$winCnts[8]}'
    WHERE
      GUBUN = '{$type}' AND DRAWNUM = '{$drawnum}'
  ";
  $db->query($sql);

  syslog(LOG_DEBUG, "{$type} 당첨금 및 당첨자 수 업데이트 완료");
}
