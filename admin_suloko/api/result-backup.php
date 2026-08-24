<?php
/**
 * 구매 티켓 당첨 결과 수신
 */

header("Content-Type: text/html; charset=UTF-8");
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
  $type        = "ticket";

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

  // 요청 데이터 유효성 확인
  if (!$requestData || !validaterequestData($requestData)) {
    printDebug($headers, $postData, $type, "INVALID_REQUEST_FORMAT");
    returnResponse("INVALID_REQUEST_FORMAT");
  }

  $success = true;
  // 환율
  $exchangeRate = getExchangeRate();

  try {
    // 당첨 결과 저장
    foreach ($requestData["data"] as $orderNo => $rank) {
      $result = updatePrize($requestData["type"], $orderNo, $rank, $exchangeRate);
      if ($result === false) {
        $success = false;
        break;
      }
    }

    if ($success) {
      returnResponse("SUCCESS");
    } else {
      returnResponse("FAILED_TO_SAVE");
    }
  } catch (Exception $e) {
    $link->rollBack();
    returnResponse("FAILED_TO_SAVE");
  }
}

// 유효성 검사
function validaterequestData($requestData) {
  // 요청 데이터가 비어 있는지 확인
  if (empty($requestData["type"]) || empty($requestData["data"])) {
    returnResponse("INVALID_REQUEST_FORMAT");
  }

  // 주문 번호 배열 추출
  $reqIds = extractOrderIds($requestData["data"]);

  // 주문 번호를 기반으로 주문 내역 확인
  $orderCount = getOrderHistoryCount($reqIds);

  // 주문 내역 개수와 요청된 주문 개수 비교
  if (count($reqIds) !== (int) $orderCount) {
    returnResponse("ORDER_DOES_NOT_EXIST");
  }

  return true;
}

// 주문 번호 배열 추출
function extractOrderIds($data) {
  $reqIds = [];

  foreach ($data as $orderNo => $rank) {
    if (empty($orderNo) || empty($rank)) {
      return [];
    }

    $reqIds[] = $orderNo;
  }
  return $reqIds;
}

// 주문 번호를 기반으로 주문 내역 개수 확인
function getOrderHistoryCount($reqIds) {
  global $db;

  $reqString = implode(",", $reqIds);

  $data = $db->get_data("SELECT COUNT(*) AS cnt FROM ORDERS WHERE ORDERS_NO IN ($reqString)");
  return $data["cnt"];
}

// 당첨 결과 저장
function updatePrize($type, $orderNo, $rankString, $exchangeRate) {
  global $db, $megaPrizes, $powerPrizes;

  $rank = explode(",", $rankString);
  $win1 = intval($rank[0]);
  $win2 = intval($rank[1]);
  $win3 = intval($rank[2]);
  $win4 = intval($rank[3]);
  $win5 = intval($rank[4]);

  $winRank = array_filter($rank, function($value) {
    return $value > 0;
  });
  sort($winRank);
  $winRanks = implode(",", $winRank) . "등";

  $defaultPrizes = ($type === "MM") ? $megaPrizes : $powerPrizes;

  $winmoney1 = $defaultPrizes[$win1];
  $winmoney2 = $defaultPrizes[$win2];
  $winmoney3 = $defaultPrizes[$win3];
  $winmoney4 = $defaultPrizes[$win4];
  $winmoney5 = $defaultPrizes[$win5];

  $totalSum = $win1 + $win2 + $win3 + $win4 + $win5;

  $isWin = "N";
  if ($totalSum > 0) {
    $isWin = "Y";
  }

  $totalUsd = $winmoney1 + $winmoney2 + $winmoney3 + $winmoney4 + $winmoney5;
  $totalKrw = (int)$totalUsd * (int)$exchangeRate;

  $sql = "
    UPDATE ORDERS
    SET
      WIN_YN = '{$isWin}',
      WIN1 = {$win1}, WIN2 = {$win2}, WIN3 = {$win3}, WIN4 = {$win4}, WIN5 = {$win5},
      WIN_MONEY_YN = 'Y',
      WIN_MONEY = {$totalKrw},
      WIN_MONEY_USD = {$totalUsd},
      WON = {$exchangeRate}
    WHERE ORDERS_NO = {$orderNo}
  ";

  try {
    $result = $db->query($sql);

    if ($result === 0) {
      throw new Exception("Failed to save order information.");
    }

    if ($isWin === "Y") {
      $info = $db->get_data("SELECT * FROM ORDERS WHERE ORDERS_NO = '{$orderNo}'");
      $member = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID = '{$info['USER_ID']}'");

      // 당첨금 지급 내역 추가
      savePrizeHistory($member, $orderNo, $totalKrw);

      // 당첨금 지급
      updateUserPrize($member["USER_ID"], $totalKrw);

      // 회원에게 당첨 문자 발송 및 고액 당첨자 발생 시 관리에게 알림 발송
      sendNotificationToAdmin($totalUsd, $totalKrw, $info, $member, $winRanks);
    }
  } catch (Exception $e) {
    return false;
  }
}

// 당첨금 지급 내역 추가
function savePrizeHistory($member, $orderNo, $totalKrw) {
  global $db;

  $n_wcash = $member["WINCASH"] + $totalKrw;
  $o_wcash = $member["WINCASH"];
  $userId  = $member["USER_ID"];

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
      '{$orderNo}',
      '{$totalKrw}',
      '{$n_wcash}',
      '{$o_wcash}',
      '{$userId}',
      '당첨금적립',
      'P'
    )
  ";
  $db->query($sql);
}

// 당첨금 지급
function updateUserPrize($userId, $totalKrw) {
  global $db;

  $sql = "
    UPDATE MEMBER SET
      WINCASH = WINCASH + '{$totalKrw}'
    WHERE
      USER_ID = '{$userId}'
  ";
  $db->query($sql);
}

// 고액 당첨자 발생 시 관리에게 알림 발송
function sendNotificationToAdmin($totalUsd, $totalKrw, $info, $member, $winRanks) {
  global $db, $fromHP, $ball_op;

  if ($totalUsd > 600) {
    $to_arr = [];

    foreach ($to_arr as $to) {
      $from        = $fromHP;
      $CODESK      = "S";
      $TEMPLET_NO  = "";
      $MEMBER_NO   = "";
      $MEMBER_NAME = "";
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

// 환율
function getExchangeRate() {
  global $db;

  $data = $db->get_data("SELECT WON FROM EXCHANGE ORDER BY EXCHANGE_NO DESC LIMIT 1");
  return $data["WON"];
}
