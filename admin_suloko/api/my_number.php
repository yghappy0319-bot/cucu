<?php
/**
 * 내 번호 등록 수신
 */

header("Content-Type: text/html; charset=UTF-8");

require_once("common.php");

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
  $type        = "my_number";

//  // IP 주소 확인
//  if ($clientIpAddress !== $usIpAddress) {
//    returnResponse("ACCESS_DENIED");
//  }

//  // 토큰 확인
//  $token = $headers["token"];
//  if (!$token) {
//    printDebug($headers, $postData, $type, "MISSING_KEY");
//    returnResponse("MISSING_KEY");
//  }
//
//  // 클라이언트 키와 토큰 일치 여부 확인
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
    $no = $requestData["no"];
    $lotto = $requestData["lotto"];

    foreach ($requestData['data'] as $row) {
      $balls = explode(",", $row);
      $ballCount = count($balls);

      $allNumeric = array_reduce($balls, function($carry, $item) {
        return $carry && is_numeric($item);
      }, true);

      if ($ballCount !== 6 || !$allNumeric) {
        returnResponse("INVALID_REQUEST_FORMAT");
      }

      // 내 번호 저장
      saveFavoriteNumber($no, $lotto, $row);
    }
    returnResponse("SUCCESS");
  } catch (Exception $e) {
    returnResponse("FAILED_TO_SAVE");
  }
}

// 유효성 검사
function validaterequestData($requestData) {
  if (empty($requestData["data"] || $requestData["no"] === "0" || empty($requestData["lotto"]))) {
    returnResponse("INVALID_REQUEST_FORMAT");
  }
  return true;
}

// 내 번호 저장
function saveFavoriteNumber($no, $lotto, $row) {
  global $db;

  $limit = $db->get_data_one("SELECT COUNT(*) AS cnt FROM MY_BALL WHERE MEMBER_NO = {$no} AND GUBUN = '{$lotto}'");

  if ($limit > 99) {
    returnResponse("EXCEEDED_LIMIT");
  }

  $isBall = $db->get_data_one("SELECT COUNT(*) AS cnt FROM MY_BALL WHERE MEMBER_NO = {$no} AND GUBUN = '{$lotto}' AND BALL1 = '{$row}'");

  // 동알한 번호가 저장되어 있다면 통과
  if ($isBall > 0) {
    return;
  }

  try {
    $db->query("INSERT INTO MY_BALL (MEMBER_NO, GUBUN, BALL1, REG_DATE) VALUES ({$no}, '{$lotto}', '{$row}', NOW())");
  } catch (Exception $e) {
    returnResponse("FAILED_TO_SAVE");
  }
}
