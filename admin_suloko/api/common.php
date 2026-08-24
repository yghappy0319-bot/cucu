<?php
require_once $_SERVER['DOCUMENT_ROOT']."/_common/config.php";

define("SUCCESS", "0000");
define("ACCESS_DENIED", "ERROR1000");
define("INVALID_REQUEST_FORMAT", "ERROR1001");
define("REQUIRED_VALUE_MISSING", "ERROR1002");
define("MISSING_KEY", "ERROR1003");
define("FAILED_TO_GET_KEY", "ERROR1004");
define("KEY_MISMATCH", "ERROR1005");
define("DUPLICATED", "ERROR1006");
define("ORDER_NOT_FOUND", "ERROR1007");
define("CANNOT_CANCEL", "ERROR1008");
define("FAILED_TO_SAVE", "ERROR1009");
define("FAILED_TO_CANCEL", "ERROR1010");
define("FAILED_TO_UPDATE_WINNINGS", "ERROR1011");
define("ORDER_DOES_NOT_EXIST", "ERROR1012");
define("EXCEEDED_LIMIT", "ERROR1013");
define("MEMBER_DOES_NOT_EXIST", "ERROR1014");
define("INSUFFICIENT_FUNDS", "ERROR1015");

$messages = [
  SUCCESS                   => "The operation was successful.",
  ACCESS_DENIED             => "Access Denied.",
  INVALID_REQUEST_FORMAT    => "The request data format is invalid.",
  REQUIRED_VALUE_MISSING    => "Required value is missing in the request.",
  MISSING_KEY               => "The key is missing.",
  FAILED_TO_GET_KEY         => "Failed to retrieve the key.",
  KEY_MISMATCH              => "Key does not match.",
  DUPLICATED                => "The operation resulted in a duplication.",
  ORDER_NOT_FOUND           => "The specified order was not found.",
  CANNOT_CANCEL             => "The order cannot be cancelled.",
  FAILED_TO_SAVE            => "Failed to save the data.",
  FAILED_TO_CANCEL          => "Failed to cancel the order.",
  FAILED_TO_UPDATE_WINNINGS => "Failed to update winngins.",
  ORDER_DOES_NOT_EXIST      => "There is an order ID that does not exist.",
  EXCEEDED_LIMIT            => "Exceeded the storage limit.",
  MEMBER_DOES_NOT_EXIST     => "This member does not exist.",
  INSUFFICIENT_FUNDS        => "Insufficient funds.",
];

function returnResponse($code, $data = null) {
  global $messages;

  $code    = defined($code) ? constant($code) : $code;
  $message = isset($messages[$code]) ? $messages[$code] : "Unknown error.";

  http_response_code(200);

  if ($code === "ERROR1000") {
    http_response_code(403);
  } else if ($code !== "0000") {
    http_response_code(400);
  }

  if ($data) {
    echo json_encode(["code" => $code, "message" => $message, "data" => $data]);
  } else {
    echo json_encode(["code" => $code, "message" => $message]);
  }
  exit;
}

// Debug
function printDebug($headers, $postData, $type, $msg) {
  $headers["data"] = $postData;
  $headers["msg"] = $msg;
  syslog(LOG_DEBUG, "[{$type}_ERROR] " . print_r($headers, true));
}
