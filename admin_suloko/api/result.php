<?php
/**
 * 구매 티켓 당첨 결과 수신
 */

header("Content-Type: text/html; charset=UTF-8");

exit;

require_once($_SERVER["DOCUMENT_ROOT"] . "/_common/config.php");
require_once("common.php");


if ($_SERVER["REQUEST_METHOD"] === "POST") {
  handlePostRequest();
}

// POST 요청 처리
function handlePostRequest() {
  global $usClientKey, $usIpAddress, $clientIpAddress, $link;

  // 요청 헤더 및 데이터 가져오기
  $headers     = apache_request_headers();
  $postData    = file_get_contents("php://input");
  $requestData = json_decode($postData, true);
  $type        = "result";

  // IP 주소 확인
  if ($clientIpAddress !== $usIpAddress) {
    returnResponse("ACCESS_DENIED");
  }

  // 토큰 확인
  $token = $headers["token"];
  if (!$token) {
    printDebug($headers, $postData, $type, "MISSING_KEY");
    returnResponse("MISSING_KEY");
  }

  // 클라이언트 키와 토큰 일치 여부 확인
  if ($usClientKey !== $token) {
    printDebug($headers, $postData, $type, "KEY_MISMATCH");
    returnResponse("KEY_MISMATCH");
  }

  // 요청 데이터 확인
  if (!$requestData || !validaterequestData($requestData)) {
    printDebug($headers, $postData, $type, "INVALID_REQUEST_FORMAT");
    returnResponse("INVALID_REQUEST_FORMAT");
  }

  // 환율
  $exchangeRate = getExchangeRate();

  // 티켓 결과 저장
  $success = processTicketResults($requestData, $exchangeRate);
  return $success ? returnResponse("SUCCESS") : returnResponse("FAILED_TO_SAVE");
}

// 유효성 검사
function validaterequestData($requestData) {
  if (empty($requestData["type"]) || empty($requestData["data"])) {
    returnResponse("INVALID_REQUEST_FORMAT");
  }

  $orderIds = array_keys($requestData["data"]);
  return count($orderIds) === getOrderHistoryCount($orderIds);
}

// 주문 번호를 기반으로 주문 내역 개수 확인
function getOrderHistoryCount($reqIds) {
  global $db;

  $reqString = implode(",", $reqIds);

  $data = $db->get_data("SELECT COUNT(*) AS cnt FROM ORDERS WHERE ORDERS_NO IN ($reqString) AND WIN_YN = 'R'");
  return (int)$data["cnt"];
}

// 환율
function getExchangeRate() {
  global $db;

  $data = $db->get_data("SELECT WON FROM EXCHANGE ORDER BY EXCHANGE_NO DESC LIMIT 1");
  return $data["WON"];
}

// 티켓 결과 처리
function processTicketResults($requestData, $exchangeRate) {
  global $megaPrizes, $powerPrizes, $new_megamillion_draw_date;
  try {
    $prizeArray = ($requestData["type"] === "MM") ? $megaPrizes : $powerPrizes;

    foreach ($requestData["data"] as $orderNo => $orderInfo) {

      if (getSecondDifference($new_megamillion_draw_date) >= 0) {
        $rank = json_decode($orderInfo["rank"],true);
        $prize = json_decode($orderInfo["prize"],true);

        $prizes = array_filter($prize, function($value) {
          return $value > 0;
        });
      } else {
        $rank = explode(",", $orderInfo);

        $prizes = array_map(function ($value) use ($prizeArray) {
          return $prizeArray[$value] ?? 0;
        }, $rank);
      }

      $winRank = array_filter($rank, function($value) {
        return $value > 0;
      });
      sort($winRank);
      $winRanks = implode(",", $winRank) . "등";

      $totalSum = array_sum($rank);
      $isWin = $totalSum > 0 ? "Y" : "N";

      $totalUsd = array_sum($prizes);
      $totalKrw = $totalUsd * $exchangeRate;

      // 디버그
      // syslog(7, "orderNo = ".$orderNo);
      // syslog(7, "isWin = ".$isWin);
      // syslog(7, "rank = ".print_r($rank,true));
      // syslog(7, "totalKrw = ".$totalKrw);
      // syslog(7, "totalUsd = ".$totalUsd);
      // syslog(7, "exchangeRate = ".$exchangeRate);
      // syslog(7, "winRanks = ".$winRanks);
      // exit;

      updateTicketResults($orderNo, $isWin, $rank, $totalKrw, $totalUsd, $exchangeRate, $winRanks);
    }
    return true;
  } catch (Exception $e) {
    return false;
  }
}

// 주문 정보에 결과 업데이트
function updateTicketResults($orderNo, $isWin, $rank, $totalKrw, $totalUsd, $exchangeRate, $winRanks) {
  global $db;

  if ($totalUsd >= "20000000") $totalKrw = $totalUsd; // 1등 발생시 원화를 달러로 대치해서 임시로 저장하고 후처리한다.

  $sql = "
    UPDATE ORDERS
    SET
      WIN_YN = '{$isWin}',
      WIN1 = {$rank[0]}, WIN2 = {$rank[1]}, WIN3 = {$rank[2]}, WIN4 = {$rank[3]}, WIN5 = {$rank[4]},
      WIN_MONEY_YN = 'Y',
      WIN_MONEY = {$totalKrw},
      WIN_MONEY_USD = {$totalUsd},
      WON = {$exchangeRate}
    WHERE ORDERS_NO = {$orderNo}
  ";

  $result = $db->query($sql);

  if ($isWin === "Y" && $result !== 0) {
    $member = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID = (SELECT USER_ID FROM ORDERS WHERE ORDERS_NO = '{$orderNo}')");
    $info   = $db->get_data("SELECT GUBUN, DRAWNUM, REG_DATE FROM ORDERS WHERE ORDERS_NO = '{$orderNo}'");

    if (!isset($member['USER_ID'])) {
      syslog(LOG_DEBUG, "[RESULT_ORDER_ERROR] ORDERS_NO = {$orderNo}");
    } else {
      savePrizeHistory($member, $orderNo, $totalKrw);
      updateUserPrize($member["USER_ID"], $totalKrw);
      sendNotificationToAdmin($totalUsd, $totalKrw, $info, $member, $winRanks);
    }
  }
}

// 당첨금 지급 내역 추가
function savePrizeHistory($member, $orderNo, $totalKrw) {
  global $db;

  $nWcash = $member["WINCASH"] + $totalKrw;
  $oWcash = $member["WINCASH"];
  $userId = $member["USER_ID"];

  $sql = "
    INSERT INTO T_WCASH_LOG (ORDERS_NO, WCASH, N_WCASH, O_WCASH, USER_ID, MEMO, STATUS)
    VALUES ('{$orderNo}', '{$totalKrw}', '{$nWcash}', '{$oWcash}', '{$userId}', '당첨금적립', 'P')
  ";
  $db->query($sql);
}

// 당첨금 지급
function updateUserPrize($userId, $totalKrw) {
  global $db;

  $sql = "
    UPDATE MEMBER SET WINCASH = WINCASH + '{$totalKrw}'
    WHERE USER_ID = '{$userId}'
  ";
  $db->query($sql);
}

// 고액 당첨자 발생 시 관리자에게 알림 발송
function sendNotificationToAdmin($totalUsd, $totalKrw, $info, $member, $winRanks) {
  global $db, $fromHP, $ball_op;

  if ($totalUsd > 600) {
    $to_arr = [];

    foreach ($to_arr as $to) {
      $from        = $fromHP;
      $CODESK      = "S";
      $TEMPLET_NO  = "0";
      $MEMBER_NO   = "0";
      $MEMBER_NAME = "0";
      $SUBJECT     = "고액당첨확인";
      $SENDMSG     = "고액 당첨이 확인되었습니다.\r\n\r\n";
      $SENDMSG    .= "회원 : {$member['NAME']} ({$member['USER_ID']})\r\n";
      $SENDMSG    .= "게임 : {$ball_op[$info["GUBUN"]]} {$info["DRAWNUM"]}회차\r\n";
      $SENDMSG    .= "등수 : {$winRanks}\r\n";
      $SENDMSG    .= "금액 : ".number_format($totalKrw)."원 (".number_format($totalUsd)."달러)";
      $sms         = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG);
    }
  } else {
    $TEMPLET_NO  = 17;
    $SMSTEMPLET  = $db->get_data("SELECT * FROM SMSTEMPLET WHERE TEMPLET_NO='{$TEMPLET_NO}'");
    $from        = $fromHP;
    $to          = $member["HP"];
    $CODESK      = "S";
    $MEMBER_NO   = $member['MEMBER_NO'];
    $MEMBER_NAME = $member['NAME'];
    $SUBJECT     = $SMSTEMPLET['SUBJECT'];
    $SENDMSG     = $SMSTEMPLET['CONTENT'];
    $SENDMSG     = str_replace('{USERID}', $member['USER_ID'], $SENDMSG);
    $SENDMSG     = str_replace('{USERNAME}', $member['NAME'], $SENDMSG);
    $SENDMSG     = str_replace('{REGDATE}', $info["REG_DATE"], $SENDMSG);
    $SENDMSG     = str_replace('{LOTTONAME}', $ball_op[$info["GUBUN"]], $SENDMSG);
    $SENDMSG     = str_replace('{WINRANK}', $winRanks, $SENDMSG);
    $SENDMSG     = str_replace('{WINMONEY}', number_format($totalKrw)."원", $SENDMSG);
    $SENDMSG     = addslashes($SENDMSG);
    $sms         = curl_sms($to, $from, $CODESK, $TEMPLET_NO, $MEMBER_NO, $MEMBER_NAME, $SUBJECT, $SENDMSG);
  }
}
