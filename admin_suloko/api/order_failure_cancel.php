<?php
/**
 * 주문 실패 처리
 * - USA 주문 데이터 저장 실패 시 주문 데이터 삭제 및 환불 처리
 */
header("Content-Type: text/html; charset=UTF-8");

require_once("common.php");

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  handlePostRequest();
}

// POST 요청 처리
function handlePostRequest() {
  syslog(LOG_DEBUG, "handlePostRequest");
  global $usClientKey, $usIpAddress, $clientIpAddress;

  // 요청 헤더 및 데이터 가져오기
  $headers     = apache_request_headers();
  $postData    = file_get_contents("php://input");
  $requestData = json_decode($postData, true);
  $type        = "order_failure_cancel";

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

  try {
    handleOrderFailed($requestData);

  } catch (Exception $e) {
    returnResponse("FAILED_TO_SAVE");
  }
}

function handleOrderFailed($requestData) {
  global $db;

  $orders = $requestData["orders"];

  for ($i = 0; $i < count($orders); $i++) {
    $info = $db->get_data("SELECT * FROM ORDERS WHERE ORDERS_NO='{$orders[$i]}'");
    $mem  = $db->get_data("SELECT * FROM MEMBER WHERE USER_ID='{$info['USER_ID']}'");

    $db->query("DELETE FROM T_CASH_LOG WHERE ORDERS_NO='{$orders[$i]}'");
    $db->query("DELETE FROM T_WCASH_LOG WHERE ORDERS_NO='{$orders[$i]}'");
    $db->query("DELETE FROM T_POINT_LOG WHERE ORDERS_NO='{$orders[$i]}'");
    $db->query("DELETE FROM T_IPOINT_LOG WHERE ORDERS_NO='{$orders[$i]}'");
    $db->query("DELETE FROM ORDERS WHERE ORDERS_NO='{$orders[$i]}'");

    $sql = "
      UPDATE
         MEMBER
      SET
        POINT    = POINT + {$info['POINT']},
        IPOINT   = IPOINT + {$info['IPOINT']},
        CASH     = CASH + {$info['CASH']},
        WINCASH  = WINCASH + {$info['WINCASH']},
        TOTPRICE = TOTPRICE - {$info['CASH']},
        TOTCNT   = TOTCNT - {$info['GAMECNT']}
      WHERE
        USER_ID = '{$info['USER_ID']}'
    ";
    $db->query($sql);

    $in_ipoint   = 0;
    $in_ipoint_p = 0;

    switch($mem['LEVEL']) {
      case 2:
        $in_ipoint = ceil(($info['CASH'] + $info['WINCASH']) / 100 * 2.5);
        break;
      case 3:
        $in_ipoint = ceil(($info['CASH'] + $info['WINCASH']) / 100 * 5);
        break;
      case 4:
        $in_ipoint = ceil(($info['CASH'] + $info['WINCASH']) / 100 * 8);
        break;
      default:
        $in_ipoint = ceil(($info['CASH'] + $info['WINCASH']) / 100 * 0.5);
        break;
    }

    $db->query("UPDATE MEMBER SET IPOINT = IPOINT - {$in_ipoint} WHERE USER_ID = '{$info['USER_ID']}'");
  }
}
