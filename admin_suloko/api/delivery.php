<?php
/**
 * 배송 상태 업데이트 수신
 */

header("Content-Type: text/html; charset=UTF-8");

require_once("common.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  handlePostRequest();
}

// POST 요청 처리
function handlePostRequest() {
  global $usClientKey, $usIpAddress, $clientIpAddress, $link;

  // 요청 헤더 및 데이터 가져오기
  $headers     = apache_request_headers();
  $postData    = file_get_contents('php://input');
  $requestData = json_decode($postData, true);
  $type        = "delivery";

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

  // 티켓 배송 상태 변경
  sendDelivery($requestData["orderNo"]);

  returnResponse("SUCCESS");
}

// 유효성 검사
function validaterequestData($requestData) {
  if (empty($requestData)) {
    returnResponse("INVALID_REQUEST_FORMAT");
  }

  $requiredFields = ["orderNo"];
  foreach ($requiredFields as $field) {
    if (!isset($requestData[$field]) || empty($requestData[$field])) {
      returnResponse("REQUIRED_VALUE_MISSING");
    }
  }
  return true;
}

// 티켓 배송 상태 변경
function sendDelivery($orderNo) {
  global $db;

  $sql = "UPDATE NON_WINING SET STATUS = 'R' WHERE ORDERS_NO = '{$orderNo}'";

  try {
    $result = $db->query($sql);
    if ($result === false) {
      throw new Exception("Failed to send delivery.");
    }
  } catch (Exception $e) {
    saveErrorLog("FAILED_TO_CANCEL", $clientIdx, $orderNo, "cancel");
    returnResponse("FAILED_TO_CANCEL");
  }
}
