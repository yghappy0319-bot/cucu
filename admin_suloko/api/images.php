<?php
/**
 * 티켓 스캔 이미지 정보 수신
 */

header("Content-Type: text/html; charset=UTF-8");

exit;

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
  $type        = "path";

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

  // 티켓 Path 저장
  $success = processTicketPath($requestData);
  return $success ? returnResponse("SUCCESS") : returnResponse("FAILED_TO_SAVE");
}

// 유효성 검사
function validaterequestData($requestData) {
  // 요청 데이터가 비어 있는지 확인
  if (empty($requestData["data"])) {
    returnResponse("INVALID_REQUEST_FORMAT");
  }

  $orderIds = array_keys($requestData["data"]);
  return count($orderIds) === getOrderHistoryCount($orderIds);
}

// 주문 번호를 기반으로 주문 내역 개수 확인
function getOrderHistoryCount($reqIds) {
  global $db;

  $reqString = implode(",", $reqIds);

  $data = $db->get_data("SELECT COUNT(*) AS cnt FROM ORDERS WHERE ORDERS_NO IN ($reqString)");
  return (int)$data["cnt"];
}

// 티켓 path 처리
function processTicketPath($requestData) {
  try {
    foreach ($requestData["data"] as $orderNo => $orderInfo) {
      $parts = explode("/", $orderInfo["image"]);
      $imgDate = $parts[0];
      $imgName = $parts[1];
      $multi = $orderInfo["multi"];
      updateTicketPath($orderNo, $imgDate, $imgName, $multi);
    }
    return true;
  } catch (Exception $e) {
    return false;
  }
}

// 주문 정보에 티켓 path 업데이트
function updateTicketPath($orderNo, $imgDate, $imgName, $multi) {
  global $db;

  // 디버그
  // syslog(7, "orderNo = " . $orderNo);
  // syslog(7, "imgDate = " . $imgDate);
  // syslog(7, "imgName = " . $imgName);
  // syslog(7, "multi = " . print_r($multi,true));
  // syslog(7, "array_sum = " . array_sum($multi));

  if (array_sum($multi) > 0) {
    $sql = "SELECT COUNT(*) FROM `ORDER_MULTIPLIER` WHERE `ORDERS_NO` = {$orderNo}";
    $chk_cnt = $db->get_data_one($sql);
    if ($chk_cnt == 1) {
      $sql = "UPDATE `ORDER_MULTIPLIER` SET `MULTI_A` = $multi[0], `MULTI_B` = $multi[1], `MULTI_C` = $multi[2], `MULTI_D` = $multi[3], `MULTI_E` = $multi[4] WHERE `ORDERS_NO` = {$orderNo}";
    } else {
      $sql = "INSERT INTO `ORDER_MULTIPLIER` (`ORDERS_NO`, `MULTI_A`, `MULTI_B`, `MULTI_C`, `MULTI_D`, `MULTI_E`) VALUES ({$orderNo},{$multi[0]},{$multi[1]},{$multi[2]},{$multi[3]},{$multi[4]})";
    }
    $db->query($sql);
  }

  $sql = "UPDATE ORDERS SET IMG_PATH = '{$imgName}', IMG_YN = 'Y', IMG_DATE = '{$imgDate}' WHERE ORDERS_NO = {$orderNo}";
  $db->query($sql);
}
