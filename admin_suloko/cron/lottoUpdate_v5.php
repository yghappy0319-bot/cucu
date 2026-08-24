<?php
  require_once "/home/super/admin/_common/config.php";
  require_once "/home/super/admin/_library/simple_html_dom.php";

  /**
   * 추첨시간 이전에 실행시 이전 회차 데이터를 가지고 옴
   * 이때 PLAYDATE 값이 꼬이는 이슈가 확인되어 추첨시간 이후
   * 추첨일 13:10분 실행시 부터 로직이 적용되도록 체크함
   */
  $now_date = date("Y-m-d H:i:s", time());
  $chk_date = date("Y-m-d", time())." ".$draw_time_num.":05:00";
  if (strtotime($now_date) < strtotime($chk_date)) {
    syslog(LOG_DEBUG, "추첨시간 이전으로 종료");
    exit;
  }

  syslog(LOG_DEBUG, "Lotto Update Start ======>");

  /**
   * 당첨 번호 처리
   */
  function exeCronWinNumberInsert($codeLK, $playDate, $winNums) {
    global $db, $megaPrizes, $powerPrizes;

    syslog(LOG_DEBUG, "{$codeLK} > 당첨 번호 저장");

    // 등수별 당첨금
    $prizes = ($codeLK === "MM") ? $megaPrizes : $powerPrizes;

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
        BALL1 = '".intval($winNums[0])."',
        BALL2 = '".intval($winNums[1])."',
        BALL3 = '".intval($winNums[2])."',
        BALL4 = '".intval($winNums[3])."',
        BALL5 = '".intval($winNums[4])."',
        BALLP = '".intval($winNums[5])."'
      WHERE
        PLAYDATE = '".$playDate."' AND
        GUBUN = '".$codeLK."'
    ";
    $db->query($sql);

    // 당첨 번호 통계 저장
    $result = exeCronWinningNumberStatisticInsert($codeLK, $playDate, $winNums);
    return $result ? true : false;
  }

  /**
   * 당첨 번호 통계 저장
   */
  function exeCronWinningNumberStatisticInsert($codeLK, $playDate, $winNums) {
    global $db;

    syslog(LOG_DEBUG, "{$codeLK} > 당첨 번호 통계 저장");

    $query = "SELECT * FROM WINNING_NUMBER_STATISTIC WHERE GUBUN='".$codeLK."' ORDER BY PLAYDATE DESC LIMIT 1";
    $lastInfo = $db->get_data($query);

    if ($lastInfo['PLAYDATE'] < $playDate) {
      $fromlast = [];
      $odd = 0;
      $even = 0;
      $section10 = 0;
      $section20 = 0;
      $section30 = 0;
      $section40 = 0;
      $section50 = 0;
      $section60 = 0;
      $section70 = 0;

      foreach ($winNums as $key => $value) {
        if ($key > 4) {
          break;
        }
        if ($value %2 == 1) {
          $odd++;
        } else {
          $even++;
        }

        if ($value >= 1 && $value <= 10) {
          $section10++;
        } else if ($value >= 11 && $value <= 20) {
          $section20++;
        } else if ($value >= 21 && $value <= 30) {
          $section30++;
        } else if ($value >= 31 && $value <= 40) {
          $section40++;
        } else if ($value >= 41 && $value <= 50) {
          $section50++;
        } else if ($value >= 51 && $value <= 60) {
          $section60++;
        } else if ($value >= 61 && $value <= 70) {
          $section70++;
        }

        for ($i = 1; $i <= 5; $i++) {
          if ($value == $lastInfo['BALL'.$i]) {
            $fromlast[] = $value;
          }
        }
      }
      $fromlastText = trim(implode(",", $fromlast));
      $drawnum = (int)$lastInfo['DRAWNUM'] + 1;

      $query = "
        INSERT INTO WINNING_NUMBER_STATISTIC (
          PLAYDATE, GUBUN, DRAWNUM, BALL1, BALL2, BALL3, BALL4, BALL5, BALLP, FROM_LAST, ODD, EVEN, SECTION10, SECTION20, SECTION30, SECTION40, SECTION50, SECTION60, SECTION70
        ) VALUES (
          '".$playDate."',
          '".$codeLK."',
          ".trim($drawnum).",
          ".trim($winNums[0]).",
          ".trim($winNums[1]).",
          ".trim($winNums[2]).",
          ".trim($winNums[3]).",
          ".trim($winNums[4]).",
          ".trim($winNums[5]).",
          ".((count($fromlast) <= 0) ? "NULL" : "'$fromlastText'").",
          ".trim($odd).",
          ".trim($even).",
          ".trim($section10).",
          ".trim($section20).",
          ".trim($section30).",
          ".trim($section40).",
          ".trim($section50).",
          ".trim($section60).",
          ".trim($section70)."
        )
      ";
      return $db->query($query);
    }
  }

  /**
   * 구매 건 중 당첨 여부, 번호 저장 및 당첨금 지급 처리
   * 중복으로 처리되지 않는다.
   */
  function exeOrdersEXEupdateSETwinPrize($codeLK, $playDate, $winNums) {
    global $db, $ball_op, $fromHP;

    $winD = array();
    $winP = array();
    $winD[0] = intval($winNums[0]);
    $winD[1] = intval($winNums[1]);
    $winD[2] = intval($winNums[2]);
    $winD[3] = intval($winNums[3]);
    $winD[4] = intval($winNums[4]);
    $winP[0] = intval($winNums[5]);

    $wininfo = $db->get_data("SELECT * FROM WININFO WHERE GUBUN = '".$codeLK."' AND PLAYDATE = '".$playDate."'");

    $winNum = implode(",", $winNums);

    $list = $db->get_list("SELECT * FROM ORDERS WHERE GUBUN = '".$codeLK."' AND PLAYDATE = '".$playDate."' AND WIN_MONEY_YN NOT IN ('Y')");

    if (!isset($list["ORDERS_NO"])) {
      return false;
    }

    // 환율
    $won = $db->get_data("SELECT * FROM EXCHANGE WHERE DATE = '".date("Y-m-d")."'");

    if (!isset($won["WON"])) {
      $won = $db->get_data("SELECT * FROM EXCHANGE ORDER BY DATE DESC");
    }

    for ($i = 0; $i < count($list["ORDERS_NO"]); $i++) {
      $price = 0;
      $win = array(0, 0, 0, 0, 0, 0);

      for ($s = 1; $s <= 5; $s++) {
        if (!isset($list["BALL".$s][$i])) {
          continue;
        }

        if ($list["BALL".$s][$i] != "") {
          $winCntD = 0;
          $winCntP = 0;

          $ball = $list["BALL".$s][$i];
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
            $winNo = 1;
          } else if ($winCntD == 5 && $winCntP == 0) {
            $winNo = 2;
          } else if ($winCntD == 4 && $winCntP == 1) {
            $winNo = 3;
          } else if ($winCntD == 4 && $winCntP == 0) {
            $winNo = 4;
          } else if ($winCntD == 3 && $winCntP == 1) {
            $winNo = 5;
          } else if ($winCntD == 3 && $winCntP == 0) {
            $winNo = 6;
          } else if ($winCntD == 2 && $winCntP == 1) {
            $winNo = 7;
          } else if ($winCntD == 1 && $winCntP == 1) {
            $winNo = 8;
          } else if ($winCntD == 0 && $winCntP == 1) {
            $winNo = 9;
          } else {
            $winNo = 0;
          }

          $winprice = $wininfo["PRIZ".$winNo] ?? 0;

          if ($winprice == "") {
            $winprice = 0;
          }

          $price += $winprice;
          $win[$s] = $winNo;
        }
      }

      $won_price = $price * $won["WON"];

      $win_tp = "N";
      if ($won_price > 0) {
        $win_tp = "Y";
      }

      $sql = "
        UPDATE ORDERS SET
          WIN1 = '".$win[1]."',
          WIN2 = '".$win[2]."',
          WIN3 = '".$win[3]."',
          WIN4 = '".$win[4]."',
          WIN5 = '".$win[5]."',
          WIN_MONEY = '".$won_price."',
          WIN_MONEY_USD = '".$price."',
          WON = '".$won['WON']."',
          WIN_YN = '".$win_tp."',
          WIN_MONEY_YN = 'Y'
        WHERE
          ORDERS_NO = '".$list['ORDERS_NO'][$i]."'
      ";
      $db->query($sql);

      if ($won_price > 0) {
        $mem = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID = '{$list['USER_ID'][$i]}'");

        if ($mem["CASH"] == "") {
          $mem["CASH"] = 0;
        }
        if ($mem["WINCASH"] == "") {
          $mem["WINCASH"] = 0;
        }

        $n_wcash = $mem["WINCASH"] + $won_price;
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
            '{$list['ORDERS_NO'][$i]}',
            '{$won_price}',
            '{$n_wcash}',
            '{$o_wcash}',
            '{$list['USER_ID'][$i]}',
            '당첨금적립',
            'P'
          )
        ";
        $db->query($sql);

        $db->query("UPDATE MEMBER SET WINCASH = WINCASH+'{$won_price}' WHERE USER_ID = '{$list['USER_ID'][$i]}'");

        // 당첨 등수 처리
        $win_rank = array();
        for ($rank_cnt = 1 ; $rank_cnt <=5 ; $rank_cnt++) {
          if ($win[$rank_cnt] > 0) array_push($win_rank, $win[$rank_cnt]."등");
        }
        $win_rank_str = implode(",", $win_rank);

        // 당첨자 문자 발송
        if ($price > 600) { // 600달러 이상 관리자에게 전송
          $to_arr = array("");
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

    // 메인 누적 당첨 정보 저장
    updateMainAccumulateInfo();

    // 메인 당첨자 목록 저장
    updateMainWinnerList();
  }

  /**
   * 메인 누적 당첨 정보 저장
   */
  function updateMainAccumulateInfo() {
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

  /**
   * 메인 당첨자 목록 저장
   */
  function updateMainWinnerList() {
    global $db;

    syslog(LOG_DEBUG, "메인 당첨자 목록 저장");

    $db->query("DELETE FROM MAIN_WINNER_LIST");

    $sql = "
      INSERT INTO
        MAIN_WINNER_LIST (ID, USER_ID, NAME, FNAME, LNAME, IMG_URL, WIN1, WIN2, WIN3, WIN4, WIN5, WIN_MONEY)
      SELECT
        O.USER_ID AS ID,
        CONCAT(LEFT(O.USER_ID, 3), REPEAT('*', LENGTH(O.USER_ID) - 3)) as USER_ID,
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
          INNER JOIN
        MEMBER AS M ON M.USER_ID = O.USER_ID
      WHERE
        (WIN1 in (1,2,3,4,5) or
         WIN2 in (1,2,3,4,5) or
         WIN3 in (1,2,3,4,5) or
         WIN4 in (1,2,3,4,5) or
         WIN5 in (1,2,3,4,5)) AND
        O.WIN_YN='Y' AND
        O.WIN_MONEY_YN='Y' AND
        O.USER_ID NOT IN ('aks44193451','joonjoon79sss@gmail.com','jwcyoointai')
    ";
    $db->query($sql);
  }

  /**
   * Oregon 당첨자 수 정보 가져오기
   * Oregon Lottery API
   */
  function getOregonWinnersCount($codeLK, $chkPlayDate) {
    // ====== 당첨자 수 API Start ======
    $formattedDate = date("n/d/y", strtotime($chkPlayDate));

    $baseUrl = "https://api2.oregonlottery.org/drawresults/ByDrawDate";

    $queryParams = [
      "gameSelector" => $codeLK,
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
    $oregonJackpotWinners = $data[0]['OregonJackpotWinners'];
    $oregonShareCounts = $data[0]['OregonShareCounts'];
    $oregonShareCounts = array_slice($oregonShareCounts, 0, 8);
    array_unshift($oregonShareCounts, $oregonJackpotWinners);

    return $oregonShareCounts;
  }

  /**
   * 당첨자 수 저장
   */
  function exeCronWinCntExeInsert($codeLK, $playdate, $winCnts) {
    global $db;

    if (array_sum($winCnts) === 0) {
      syslog(LOG_DEBUG, "{$codeLK} > 당첨자 수 업데이트 안됨");
      return;
    }

    syslog(LOG_DEBUG, "{$codeLK} > 당첨자 수 저장");

    $sql = "
      UPDATE WININFO SET
        PRIZCNT1 = '".$winCnts[0]."',
        PRIZCNT2 = '".$winCnts[1]."',
        PRIZCNT3 = '".$winCnts[2]."',
        PRIZCNT4 = '".$winCnts[3]."',
        PRIZCNT5 = '".$winCnts[4]."',
        PRIZCNT6 = '".$winCnts[5]."',
        PRIZCNT7 = '".$winCnts[6]."',
        PRIZCNT8 = '".$winCnts[7]."',
        PRIZCNT9 = '".$winCnts[8]."'
      WHERE
        PLAYDATE = '".$playdate."' AND
        GUBUN = '".$codeLK."'
    ";
    $db->query($sql);
  }

  /**
   * =========================================================================================================
   */

  // 당첨번호, 당첨금 처리
  $nTime   = time();
  $weekNum = date("w", $nTime);
  $hour    = date("H", $nTime);

  // DEBUG - 강제로 실행시킬 경우 true로 변경
  $PB_exe = false;
  $MM_exe = false;

  /**
   * 파워볼 - PB
   */
  if ((($weekNum == 0 || $weekNum == 2 || $weekNum == 4) && ($hour > 10 && $hour < 21)) || $PB_exe) {
    $codeLK  = "PB";
    $chkPlayDate = date("Y-m-d", strtotime("-1 day"));
    syslog(LOG_DEBUG, "{$codeLK} > playDate : {$chkPlayDate}");

    $isDrawNumbers = $db->get_data("SELECT count(*) AS CNT FROM WININFO WHERE GUBUN = '{$codeLK}' AND PLAYDATE = '{$chkPlayDate}' AND BALL1 IS NOT NULL");
    $isNextJackpot = $db->get_data("SELECT count(*) AS CNT FROM WININFO WHERE GUBUN = '{$codeLK}' AND PRIZ1 > 0 AND PLAYDATE > '{$chkPlayDate}' LIMIT 1");
    $isNumberOfWin = $db->get_data("SELECT count(*) AS CNT FROM WININFO WHERE GUBUN = '{$codeLK}' AND PLAYDATE = '{$chkPlayDate}' AND PRIZCNT9 > 0");

    if ($isDrawNumbers["CNT"] > 0 && $isNextJackpot["CNT"] > 0 && $isNumberOfWin["CNT"] > 0) {
      syslog(LOG_DEBUG, "PB > 이미 업데이트가 완료되어 중지 합니다.");
      syslog(LOG_DEBUG, "Lotto Update Done ======<");
      exit;
    }

    // 당첨 번호 저장 및 당첨금 처리
    if ($isDrawNumbers["CNT"] == 0) {
      // ====== 당첨번호 API Start ======
      $url  = "https://www.powerball.com/v1/gameapi/numbers?gamecode=powerball";
      $html = file_get_html($url);

      // 추첨일
      $titleDate   = $html->find(".title-date");
      $dateString  = $titleDate[0]->plaintext;
      $newPlayDate = date("Y-m-d", strtotime($dateString));

      syslog(LOG_DEBUG, "PB > 당첨 번호 API, newPlayDate: {$newPlayDate}");

      // 당첨번호
      $winNums = array();
      foreach ($html->find(".item-powerball") as $item) {
        array_push($winNums, $item->plaintext);
      }

      // 초기화
      $html->clear();

      if (count($winNums) != 6) {
        syslog(LOG_DEBUG, "PB > api 당첨 번호 가져오기 오류: " . print_r($winNums, true));
        exit;
      }

      if ($chkPlayDate == $newPlayDate) {
        syslog(LOG_DEBUG, "PB > 당첨 번호 처리");
        $result = exeCronWinNumberInsert($codeLK, $chkPlayDate, $winNums);

        if ($result) {
          // 주문 건 처리 (당첨 여부 및 당첨금 지급)
          syslog(LOG_DEBUG, "PB > 주문 건 당첨 처리");
          exeOrdersEXEupdateSETwinPrize($codeLK, $chkPlayDate, $winNums);
        }
      }
    }

    // 다음 회차 당첨금 저장
    if ($isNextJackpot["CNT"] == 0) {
      // ====== 다음 회차 당첨금 API Start ======
      $url  = "https://www.powerball.com/v1/gameapi/next-drawing?gamecode=powerball";
      $html = file_get_html($url);

      // 다음 회차 추첨일
      $titleDate    = $html->find(".title-date");
      $dateString   = $titleDate[0]->plaintext;
      $playDateNext = date("Y-m-d", strtotime($dateString));

      syslog(LOG_DEBUG, "PB > 다음 회차 당첨금 API, playDateNext: {$playDateNext}");

      // 다음 회차 당첨금
      $winMoney      = $html->find(".game-jackpot-number");
      $winMoneySting = $winMoney[0]->plaintext;
      $winMoneyTmp   = str_replace("$", "", $winMoneySting);

      // 초기화
      $html->clear();

      if (strpos($winMoneyTmp, " Billion") == true) {
        $winMoneyTmp = str_replace(" Billion", "", $winMoneyTmp);
        $nextJackpot = $winMoneyTmp * 1000000000;
      } else if (strpos($winMoneyTmp, " Million") == true) {
        $winMoneyTmp = str_replace(" Million", "", $winMoneyTmp);
        $nextJackpot = $winMoneyTmp * 1000000;
      }

      if (empty($nextJackpot) == false && $chkPlayDate < $playDateNext) {
        syslog(LOG_DEBUG, "PB > 다음 회차 당첨금 저장");

        // 당첨/이월 구분
        $isType = ($nextJackpot <= 20000000) ? "Y" : "N";

        $db->query("UPDATE WININFO SET IS_TYPE = '{$isType}' WHERE GUBUN = '{$codeLK}' AND PLAYDATE = '{$chkPlayDate}'");
        $db->query("UPDATE WININFO SET PRIZ1 = '{$nextJackpot}' WHERE GUBUN = '{$codeLK}' AND PLAYDATE = '{$playDateNext}'");
        $db->query($sql);
      }
    }

    // 당첨자 수 저장
    if ($isNumberOfWin["CNT"] == 0) {
      $oregonCounts = getOregonWinnersCount($codeLK, $chkPlayDate);
      exeCronWinCntExeInsert($codeLK, $chkPlayDate, $oregonCounts);
    }
  }

  /**
   * 메가밀리언 - MM
   */
  if ((($weekNum == 3 || $weekNum == 6 ) && ( $hour > 11 && $hour < 21)) || $MM_exe) {
    $codeLK = "MM";
    $chkPlayDate = date("Y-m-d", strtotime(" -1 day"));
    syslog(LOG_DEBUG, "{$codeLK} > playDate : {$chkPlayDate}");

    $isDrawNumbers = $db->get_data("SELECT count(*) AS CNT FROM WININFO WHERE GUBUN = '{$codeLK}' AND PLAYDATE = '{$chkPlayDate}' AND BALL1 IS NOT NULL");
    $isNextJackpot = $db->get_data("SELECT count(*) AS CNT FROM WININFO WHERE GUBUN = '{$codeLK}' AND PRIZ1 > 0 AND PLAYDATE > '{$chkPlayDate}' LIMIT 1");
    $isNumberOfWin = $db->get_data("SELECT count(*) AS CNT FROM WININFO WHERE GUBUN = '{$codeLK}' AND PLAYDATE = '{$chkPlayDate}' AND PRIZCNT9 > 0");

    if ($isDrawNumbers["CNT"] > 0 && $isNextJackpot["CNT"] > 0 && $isNumberOfWin["CNT"] > 0) {
      syslog(LOG_DEBUG, "MM > 이미 업데이트가 완료되어 중지 합니다.");
      syslog(LOG_DEBUG, "Lotto Update Done ======<");
      exit;
    }

    // 당첨 번호 저장 및 당첨금 처리
    if ($isDrawNumbers["CNT"] == 0) {
      // ====== 당첨번호 API Start ======
      $url  = "https://www.megamillions.com/cmspages/utilservice.asmx/GetLatestDrawData";
      $html = file_get_html($url);

      $xml = simplexml_load_string($html);
      $jsonString = (string)$xml;
      $json = json_decode($jsonString, true);

      // 추첨일
      $drawing     = $json["Drawing"];
      $newPlayDate = date("Y-m-d", strtotime($drawing["PlayDate"]));

      syslog(LOG_DEBUG, "MM > 당첨 번호 API, newPlayDate: {$newPlayDate}");

      // 당첨번호
      $winNums = array($drawing["N1"], $drawing["N2"], $drawing["N3"], $drawing["N4"], $drawing["N5"]);
      array_push($winNums, $drawing["MBall"]);

      // 초기화
      $html->clear();

      if (count($winNums) != 6) {
        syslog(LOG_DEBUG, "MM > api 당첨 번호 가져오기 오류: " . print_r($winNums, true));
        exit;
      }

      if ($chkPlayDate == $newPlayDate) {
        syslog(LOG_DEBUG, "MM > 당첨 번호 처리");
        $result = exeCronWinNumberInsert($codeLK, $chkPlayDate, $winNums);

        if ($result) {
          // 주문 건 처리 (당첨 여부 및 당첨금 지급)
          syslog(LOG_DEBUG, "MM > 주문 건 당첨 처리");
          exeOrdersEXEupdateSETwinPrize($codeLK, $chkPlayDate, $winNums);
        }
      }
    }

    // 다음 회차 당첨금 저장
    if ($isNextJackpot["CNT"] == 0) {
      // ====== 다음 회차 당첨금 API Start ======
      $url  = "https://www.megamillions.com/cmspages/utilservice.asmx/GetLatestDrawData";
      $html = file_get_html($url);

      $xml       = simplexml_load_string($html);
      $xmlString = (string)$xml;
      $json      = json_decode($xmlString, true);

      // 다음 회차 추첨일
      $playDateNext = date("Y-m-d", strtotime($json["NextDrawingDate"]));

      syslog(LOG_DEBUG, "MM > 다음 회차 당첨금 API, playDateNext: {$playDateNext}");

      // 다음 회차 당첨금
      $jackpot     = $json["Jackpot"];
      $nextJackpot = $jackpot["NextPrizePool"];

      // 초기화
      $html->clear();

      if (empty($nextJackpot) == false && $chkPlayDate < $playDateNext) {
        syslog(LOG_DEBUG, "MM > 다음 회차 당첨금 저장");

        // 당첨/이월 구분
        $isType = ($nextJackpot <= 20000000) ? "Y" : "N";

        $db->query("UPDATE WININFO SET IS_TYPE = '{$isType}' WHERE GUBUN = '{$codeLK}' AND PLAYDATE = '{$chkPlayDate}'");
        $db->query("UPDATE WININFO SET PRIZ1 = '{$nextJackpot}', IS_TYPE = '{$isType}' WHERE GUBUN = '{$codeLK}' AND PLAYDATE = '{$playDateNext}'");
      } else {
        syslog(LOG_DEBUG, "MM > 다음 회차 당첨금 업데이트 안됨");
      }
    }

    // 당첨자 수 저장
    if ($isNumberOfWin["CNT"] == 0) {
      $oregonCounts = getOregonWinnersCount($codeLK, $chkPlayDate);
      exeCronWinCntExeInsert($codeLK, $chkPlayDate, $oregonCounts);
    }
  }

  echo "OK";
  syslog(LOG_DEBUG, "Lotto Update Done ======<");
?>
