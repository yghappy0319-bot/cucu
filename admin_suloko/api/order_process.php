<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_common/common.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_ORDERS.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_CASH_LOG.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_WCASH_LOG.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_POINT_LOG.php";
require_once $_SERVER['DOCUMENT_ROOT']."/_library/function_T_IPOINT_LOG.php";

function 파일명생성() {
    $microtime = microtime(true);
    $milliseconds = sprintf("%03d", ($microtime - floor($microtime)) * 1000);
    return date('YmdHis', $microtime) . $milliseconds;
}

function handleTicketPurchase($requestData) {
  global $db, $fromHP, $deadline_time_num, $fname, $fmail, $new_megamillion_play_date;

  $orderNo      = 0;
  $orderNoArray = [];

  $no           = $requestData["no"];
  $lotto        = $requestData["lotto"];
  $drawnum      = $requestData["drawnum"];
  $playdate     = $requestData["playdate"];
  $type         = $requestData["type"];
  $count        = $requestData["count"];
  $idxs         = $requestData["idxs"];
  $games        = $requestData["games"];
  $cash         = $requestData["cash"];
  $winnings     = $requestData["winnings"];
  $mileage      = $requestData["mileage"];
  $point        = $requestData["point"];
  $initCash     = $requestData["cash"];
  $initWinnings = $requestData["winnings"];
  $initMileage  = $requestData["mileage"];
  $initPoint    = $requestData["point"];
  $seller       = $requestData["seller"];

  // 구입 단가
  if ($lotto == "MM" && getSecondDifference($new_megamillion_play_date) >= 0) {
    $unitPrice  = 10000;
  } else {
    $unitPrice  = 6000;
  }

  $payment      = $unitPrice * $count;
  $member       = $db->get_data("SELECT * FROM MEMBER WHERE MEMBER_NO = {$no} LIMIT 1");

  if ($member["USER_ID"] === "") {
    returnResponse("MEMBER_DOES_NOT_EXIST");
  }

  if ($member['WINCASH'] < $winnings && $winnings > 0) {
    returnResponse("INSUFFICIENT_FUNDS");
  }

  if ($member['POINT'] < $point && $point > 0) {
    returnResponse("INSUFFICIENT_FUNDS");
  }

  if ($member['IPOINT'] < $mileage && $mileage > 0) {
    returnResponse("INSUFFICIENT_FUNDS");
  }

  $totalPrice = $payment - $point - $mileage - $winnings;

  if ($totalPrice < 0) {
    returnResponse("INVALID_REQUEST_FORMAT");
  }

  if ($member['CASH'] < $totalPrice) {
    returnResponse("INSUFFICIENT_FUNDS");
  }

  // SLK-356 값검증 추가
  if ($payment == 0) {
    returnResponse("INVALID_REQUEST_FORMAT");
  }

  // NaN 체크
  if (is_nan($cash) || is_nan($winnings) || is_nan($mileage) || is_nan($point)) {
    returnResponse("INVALID_REQUEST_FORMAT");
  }

  // 총 금액과 비교
  if ((float)($cash + $winnings + $mileage + $point) !== (float) $payment) {
    returnResponse("INVALID_REQUEST_FORMAT");
  }

  // 이메일 발송 용도의 데이터
  $GAMEINFO = "";
  $gameCount = 1;
  foreach ($games as $game) {
    $ballNum = 1;
    $newVAL = [];

    // 구매 건 별 분할된 변수 초기화
    $splitGamecnt  = 0;
    $splitTotal    = 0;
    $splitCash     = 0;
    $splitWinnings = 0;
    $splitMileage  = 0;
    $splitPoint    = 0;



    foreach ($game as $row) {
      if (isset($row)) {
        $GAMEINFO .= $gameCount . "번 게임: " . $row . "<br>";
        $newVAL['BALL' . $ballNum] = $row;

        $splitGamecnt++;
      } else {
        $newVAL['BALL' . $ballNum] = "";
      }
      $ballNum++;
      $gameCount++;
    }
      $ORDERS_NO = $db->get_data_one("SELECT MAX(ORDERS_NO) as ORDERS_NO FROM ORDERS") + 1;


    // 구매 건 결제금액
    $splitAmount = $splitTotal = $unitPrice * $splitGamecnt;

    // 캐시 결제 금액
    if ($cash > 0) {
      $splitCash = min($splitTotal, $cash); // 사용 캐시
      $cash -= $splitCash; // 사용 캐시 차감
      $splitTotal -= $splitCash; // 남은 금액
    }
    // 당첨금 결제 금액
    if ($winnings > 0) {
      $splitWinnings = min($splitTotal, $winnings); // 사용 당첨금
      $winnings -= $splitWinnings; // 사용 당첨금 차감
      $splitTotal -= $splitWinnings; // 남은 금액
    }
    // 마일리지 결제 금액
    if ($mileage > 0) {
      $splitMileage = min($splitTotal, $mileage); // 사용 마일리지
      $mileage -= $splitMileage; // 사용 마일리지 차감
      $splitTotal -= $splitMileage; // 남은 금액
    }
    // 포인트 결제 금액
    if ($point > 0) {
      $splitPoint = min($splitTotal, $point); //사용 포인트
      $point -= $splitPoint; // 사용 포인트 차감
      $splitTotal -= $splitPoint; // 남은 금액
    }

    $newVAL['mode']          = "insert";
    $newVAL['USER_ID']       = $member['USER_ID'];
    $newVAL['HP']            = $member['HP'];
    $newVAL['EMAIL']         = $member['EMAIL'];
    $newVAL['PAYMENT']       = $splitAmount;
    $newVAL['CASH']          = $splitCash;
    $newVAL['WINCASH']       = $splitWinnings;
    $newVAL['POINT']         = $splitPoint;
    $newVAL['IPOINT']        = $splitMileage;
    $newVAL['VAT']           = 0;
    $newVAL['AUTHNUM']       = "";
    $newVAL['CARDCD']        = "";
    $newVAL['KIOSK_NO']      = $member['KIOSK_NO'];
    $newVAL['AGENT_NO']      = 1; // 3Dsystem 으로 강제 지정 SLK-1316
    $newVAL['AGENT_NO2']     = 0; // --
    $newVAL['PATNER_NO']     = (isset($mem['PATNER_NO'])) ? $mem['PATNER_NO'] : 0;
    $newVAL['GOODS_NO']      = 0;
    $newVAL['GUBUN']         = $lotto;
    $newVAL['GUBUNO']        = $type;
    $newVAL['DRAWNUM']       = $drawnum;
    $newVAL['PLAYDATE']      = $playdate;
    $newVAL['GAMECNT']       = $splitGamecnt;

    // SITE_CONFIG의 CODE1 사용. 한국시간 오후4시 이후면 해당일 최초 1회만 CODE1+1 업데이트 후 그 값 계속 사용 (다음날 4시 이후에 다시 +1)
    // SITE_CONFIG 테이블에 CODE1_DATE (DATE 타입) 컬럼 필요
    $koreaTz       = new DateTimeZone('Asia/Seoul');
    $nowKorea      = new DateTime('now', $koreaTz);
    $koreaDate     = $nowKorea->format('Y-m-d');
    $isAfter4PM    = ((int)$nowKorea->format('H') >= 16);
    $siteConfig    = $db->get_data("SELECT CODE1, CODE1_DATE FROM SITE_CONFIG LIMIT 1");
    $code1         = isset($siteConfig['CODE1']) ? (int)$siteConfig['CODE1'] : 0;
    $code1Date     = isset($siteConfig['CODE1_DATE']) ? trim($siteConfig['CODE1_DATE']) : '';

    if ($isAfter4PM && $code1Date !== $koreaDate) {
      $db->query("UPDATE SITE_CONFIG SET CODE1 = CODE1 + 1, CODE1_DATE = '{$koreaDate}' LIMIT 1");
      $code1 = $code1 + 1;
    }

    $newVAL['CODE1'] = (string)$code1;

    $newVAL['IMG_PATH']      = 파일명생성()."_a.jpg"; // --
    $newVAL['IMG_YN']        = ""; // --
    $newVAL['IMG_DATE']      = "0000-00-00"; // --
    $newVAL['PRINT_YN']      = ""; // --
    $newVAL['SIGN_YN']       = ""; // --
    $newVAL['WIN1']          = "0"; // --
    $newVAL['WIN2']          = "0"; // --
    $newVAL['WIN3']          = "0"; // --
    $newVAL['WIN4']          = "0"; // --
    $newVAL['WIN5']          = "0"; // --
    $newVAL['WIN_MONEY']     = "0"; // --
    $newVAL['WIN_MONEY_USD'] = "0"; // --
    $newVAL['INVOCE_YN']     = ""; // --
    $newVAL['WIN_YN']        = 'R';
    $newVAL['SELLER_GUBUN']  = $seller;
    $newVAL['DATE']          = date("Y-m-d");
    $newVAL['ORDERS_NO']     = $ORDERS_NO;
    $newVAL['UNIQNUM']       = ($orderNo > 0) ? $orderNo : $newVAL['ORDERS_NO']; // 주문 고유값으로 처음 ORDERS_NO 저장

    if ($splitTotal != 0) {
      syslog(LOG_DEBUG, "[ORDER_". $newVAL['ORDERS_NO']."] 금액오류!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");
    }

    F_ORDERS($newVAL);


      // 로그 저장용 기준 ORDERS_NO
    if ($orderNo === 0) {
      $orderNo = $newVAL['ORDERS_NO'];
    };

    $ordersNoArray[] = $newVAL['ORDERS_NO'];
  }

  // 캐시 사용 로그 (차감)
  $cash_log = array();
  $cash_log['mode']        = "insert";
  $cash_log['CASH_NO']     = 0;
  $cash_log['ORDERS_NO']   = $orderNo;
  $cash_log['WINNING_NO']  = 0;
  $cash_log['INVOCE_NO']   = 0;
  $cash_log['CASH_LOG_NO'] = 0;
  $cash_log['PRICE']       = 0;
  $cash_log['CASH']        = $initCash;
  $cash_log['N_CASH']      = $member['CASH'] - $initCash;
  $cash_log['O_CASH']      = $member['CASH'];
  $cash_log['USER_ID']     = $member['USER_ID'];
  $cash_log['MEMO']        = "티켓구매사용";
  $cash_log['STATUS']      = "M";
  F_T_CASH_LOG($cash_log);

  // 당첨금 사용 로그 (차감)
  if ($initWinnings > 0) {
    $wcash_log = array();
    $wcash_log['mode']        = "insert";
    $wcash_log['ORDERS_NO']   = $orderNo;
    $wcash_log['INVOCE_NO']   = 0;
    $wcash_log['CASH_LOG_NO'] = 0;
    $wcash_log['PRICE']       = 0;
    $wcash_log['WCASH']       = $initWinnings;
    $wcash_log['N_WCASH']     = $member['WINCASH'] - $initWinnings;
    $wcash_log['O_WCASH']     = $member['WINCASH'];
    $wcash_log['USER_ID']     = $member['USER_ID'];
    $wcash_log['MEMO']        = "당첨금사용";
    $wcash_log['STATUS']      = "M";
    F_T_WCASH_LOG($wcash_log);
  }



  // 포인트 사용 로그 (차감)
  if ($initPoint > 0) {
    $coupon = $db->get_data("SELECT * FROM COUPON_LOG WHERE MEMBER_NO = '{$member['MEMBER_NO']}' LIMIT 1");

    $point_log = array();
    $point_log['mode']        = "insert";
    $point_log['COUPON_NO']   = (isset($coupon['COUPON_NO'])) ? $coupon['COUPON_NO'] : 0;
    $point_log['ORDERS_NO']   = $orderNo;
    $point_log['CASH_LOG_NO'] = 0;
    $point_log['POINT']       = $initPoint;
    $point_log['N_POINT']     = $member['POINT'] - $initPoint;
    $point_log['O_POINT']     = $member['POINT'];
    $point_log['USER_ID']     = $member['USER_ID'];
    $point_log['MEMO']        = "티켓구매사용";
    $point_log['STATUS']      = "M";

    F_T_POINT_LOG($point_log);
  }

  // 마일리지 사용 로그 (차감)
  if ($initMileage > 0) {
    $ipoint_log = array();
    $ipoint_log['mode']        = "insert";
    $ipoint_log['ORDERS_NO']   = $orderNo;
    $ipoint_log['CASH_LOG_NO'] = 0;
    $ipoint_log['WINNING_NO']  = 0;
    $ipoint_log['IPOINT']      = $initMileage;
    $ipoint_log['N_IPOINT']    = $member['IPOINT'] - $initMileage;
    $ipoint_log['O_IPOINT']    = $member['IPOINT'];
    $ipoint_log['USER_ID']     = $member['USER_ID'];
    $ipoint_log['MEMO']        = "티켓구매사용";
    $ipoint_log['STATUS']      = "M";
    F_T_IPOINT_LOG($ipoint_log);
  }

    // 게임 구매자들 쇼핑몰 구매처리
    $numbers = [9, 14, 10, 11, 5];
    $random = $numbers[array_rand($numbers)];

    $pro = $db->get_data("SELECT * FROM PRODUCT WHERE idx = {$random} ");

    $sql = "insert into PRODUCT_ORDERS set ";
    $sql .= "product_idx = {$pro['idx']}, ";
    $sql .= "midx = {$member['MEMBER_NO']}, ";
    $sql .= "view_status = '1', "; // 사용자쪽에 노출 안되게
    $sql .= "status = '1', ";
    $sql .= "quantity = 1, ";
    $sql .= "product_name = '{$pro['product_name']}', ";
    $sql .= "order_price = {$pro['product_price']}, ";
    $sql .= "order_delivery = 3000, ";
    $sql .= "order_name = '{$member['NAME']}', ";
    $sql .= "order_phone = '{$member['HP']}', ";
    $sql .= "order_zipcode = '', ";
    $sql .= "order_address1 = '', ";
    $sql .= "order_address2 = '', ";
    $sql .= "order_memo = '{$lotto}', ";
    $sql .= "regdate = now() ";
    $db->query($sql);


    $sql = "
    UPDATE
      MEMBER
    SET
      POINT    = POINT - {$initPoint},
      IPOINT   = IPOINT - {$initMileage},
      CASH     = CASH - {$initCash},
      WINCASH  = WINCASH - {$initWinnings},
      TOTPRICE = TOTPRICE + {$initCash},
      TOTCNT   = TOTCNT + {$count}
    WHERE
      USER_ID  = '{$member['USER_ID']}'
  ";
  $db->query($sql);

  /* (Start) 마일리지 적립 */
  $in_ipoint   = 0;
  $in_ipoint_p = 0;

  // 브라운 0.5,실버2.5, 골드 5, 플래티넘 8 마일리지 적립
  // 적립대상 - CASH(캐시) + WINCASH(당첨금)
  switch($member['LEVEL']) {
    case 2:
      $in_ipoint   = ceil(($initCash + $initWinnings) / 100 * 2.5);
      $in_ipoint_p = 2.5;
    break;
    case 3:
      $in_ipoint   = ceil(($initCash + $initWinnings) / 100 * 5);
      $in_ipoint_p = 5;
    break;
    case 4:
      $in_ipoint   = ceil(($initCash + $initWinnings) / 100 * 8);
      $in_ipoint_p = 8;
    break;
    default:
      $in_ipoint   = ceil(($initCash + $initWinnings) / 100 * 0.5);
      $in_ipoint_p = 0.5;
    break;
  }

  if ($initWinnings == '' ) {
    $initWinnings = 0;
  }

  // 마일리지 사용 로그 (적립)
  if ($in_ipoint > 0) {
    $in_ipoint_log = array();
    $in_ipoint_log['mode']        = "insert";
    $in_ipoint_log['ORDERS_NO']   = $orderNo;
    $in_ipoint_log['WINNING_NO']  = 0;
    $in_ipoint_log['CASH_LOG_NO'] = 0;
    $in_ipoint_log['IPOINT']      = $in_ipoint;
    $in_ipoint_log['N_IPOINT']    = $member['IPOINT'] - $initWinnings + $in_ipoint;
    $in_ipoint_log['O_IPOINT']    = $member['IPOINT'] - $initWinnings;
    $in_ipoint_log['USER_ID']     = $member['USER_ID'];
    $in_ipoint_log['MEMO']        = "티켓구매 회원레벨 적립 " . $in_ipoint_p . "%";
    $in_ipoint_log['STATUS']      = "P";
    F_T_IPOINT_LOG($in_ipoint_log);
  }

  $sql = "
    UPDATE
      MEMBER
    SET
      IPOINT = IPOINT + {$in_ipoint}
    WHERE
      USER_ID = '{$member['USER_ID']}'
  ";
  $db->query($sql);
  /* (End) 마일리지 적립 */


  /* (Start) SMS,EMAIL 발송 */
  $TEMPLET_NO  = 1;
  $SMSTEMPLET  = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='{$TEMPLET_NO}' LIMIT 1");
  $ORDERTIME   = date("Y년 m월 d일 H시 i분");
  $CURRENTHOUR = date('H');

  // 마감 시간 이전 여부 확인
  if ($CURRENTHOUR < $deadline_time_num) {
    $SCANTIME = date("Y년 m월 d일") . " " . ($deadline_time_num + 2) . ":00 이전";
  } else {
    $SCANTIME = date("Y년 m월 d일", strtotime("+1 days")) . " 오전 6시 이후";
  }

  $from        = $fromHP;
  $to          = $member["HP"];
  $CODESK      = "S";
  $MEMBER_NO   = $member['MEMBER_NO'];
  $MEMBER_NAME = $member['NAME'];
  $SUBJECT     = $SMSTEMPLET['SUBJECT'];
  $SENDMSG     = $SMSTEMPLET['CONTENT'];
  $GUBUN_SMS   = ($lotto == 'MM') ? "메가밀리언" : "파워볼"; // 게임명
  $GAMECNT_SMS = $count; // 게임수
  $DRAWNUM_SMS = $drawnum; // 회차
  $SENDMSG     = addslashes($SENDMSG);
  $SENDMSG     = str_replace('{GUBUN}', $GUBUN_SMS, $SENDMSG);
  $SENDMSG     = str_replace('{GAMENO}', $DRAWNUM_SMS, $SENDMSG);
  $SENDMSG     = str_replace('{GAMECNT}', $GAMECNT_SMS, $SENDMSG);
  $sms         = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG);

  if ($member['EMAIL'] != '') {
    $TEMPLET_NO   = 7;
    $EMAILTEMPLET = $db->get_data("SELECT * FROM EMAILTEMPLET WHERE TEMPLET_NO='{$TEMPLET_NO}' LIMIT 1");
    $to           = $member['EMAIL'];
    $subject      = $EMAILTEMPLET['SUBJECT'];
    $content      = $EMAILTEMPLET['CONTENT'];
    $content      = addslashes($content);
    $content      = str_replace('{GUBUN}', $GUBUN_SMS, $content);
    $content      = str_replace('{GAMENO}', $DRAWNUM_SMS, $content);
    $content      = str_replace('{ORDERTIME}', $ORDERTIME, $content);
    $content      = str_replace('{SCANTIME}', $SCANTIME, $content);
    $content      = str_replace('{GAMEINFO}', $GAMEINFO, $content);
    $type         = "2";

    if ($EMAILTEMPLET['STOPYN'] == 'N') {
      // mailer($fname, $fmail, $to, $subject, $content, $type, $member['NAME'], $member['MEMBER_NO'], $TEMPLET_NO);
      // AWS SES 클라이언트 생성
      $sesClient = initializeSesClient();
      if ($sesClient) {
        sendMail($sesClient, $fname, $fmail, $to, $subject, $content, $type, $member['NAME'], $member['MEMBER_NO'], $TEMPLET_NO);
      } else {
        syslog(LOG_DEBUG, "Failed to initialize SES Client");
      }
    }
  }
  /* (End) SMS,EMAIL 발송 */

  return $ordersNoArray;
};
