<?php
/**
 * 주문 결제 요청 수신
 */
header("Content-Type: text/html; charset=UTF-8");

require_once("common.php");
require_once("order_process.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  handlePostRequest();
}

// POST 요청 처리
function handlePostRequest() {
  global $usClientKey, $usIpAddress, $clientIpAddress;

  // 요청 헤더 및 데이터 가져오기
  $headers     = apache_request_headers();
  $postData    = file_get_contents("php://input");
  $requestData = json_decode($postData, true);
  $type        = "order";

  // IP 주소 확인
//  if ($clientIpAddress !== $usIpAddress) {
//    returnResponse("ACCESS_DENIED");
//  }

  // 토큰 확인
//  $token = $headers["token"];
//  if (!$token) {
//    printDebug($headers, $postData, $type, "MISSING_KEY");
//    returnResponse("MISSING_KEY");
//  }

  // 클라이언트 키와 토큰 일치 여부 확인
//  if ($usClientKey !== $token) {
//    printDebug($headers, $postData, $type, "KEY_MISMATCH");
//    returnResponse("KEY_MISMATCH");
//  }

  // 요청 데이터 유효성 확인
  if (!$requestData || !validaterequestData($requestData)) {
    printDebug($headers, $postData, $type, "INVALID_REQUEST_FORMAT");
    returnResponse("INVALID_REQUEST_FORMAT");
  }

  try {
    $orders = handleTicketPurchase($requestData);
    returnResponse("SUCCESS", $orders);
  } catch (Exception $e) {
    returnResponse("FAILED_TO_SAVE");
  }
}

// 유효성 검사
function validaterequestData($requestData) {
  // 요청 데이터가 비어 있는지 확인
  if (empty($requestData["no"] || empty($requestData["lotto"]))) {
    returnResponse("INVALID_REQUEST_FORMAT");
  }

  return true;
}
